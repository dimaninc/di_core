<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 07.07.2015
 * Time: 17:25
 */

namespace diCore\Controller;

use diCore\Entity\Comment\Model;
use diCore\Entity\User\Model as User;
use diCore\Tool\Auth as AuthTool;
use diCore\Tool\Mail\Queue;

class Comment extends \diBaseController
{
	/** @var \diComments  */
	private $Comments;

	protected $targetType;
	protected $targetId;
	protected $template;

	protected $userType;
	protected $userId;

	/**
	 * @var User
	 */
	protected $targetCommentOwner;
	/**
	 * @var User
	 */
	protected $commenter;
	/**
	 * @var \diModel
	 */
	protected $target;
	/**
	 * @var Model
	 */
	protected $targetComment;
	/**
	 * @var Model
	 */
	protected $newComment;

	public function __construct($params = [])
	{
		parent::__construct($params);

		$this->targetType = \diRequest::post('target_type', 0);
		$this->targetId = \diRequest::post('target_id', 0);
		$this->template = \diRequest::post('template', '');

		$this
			->checkAuth()
			->initTpl();

		$this->Comments = \diComments::create($this->targetType, $this->targetId);
		$this->Comments
			->setTpl($this->getTpl())
			->setTwig($this->getTwig());
	}

	public function blockAction()
	{
		$this->response([
			'html' => $this->Comments->getBlockHtml(),
			'total_count' => $this->Comments->getTotalCount(),
		]);
	}

	/** @deprecated  */
	public function addAction()
	{
		return $this->saveAction();
	}

	public function saveAction()
	{
		$result = [
			'ok' => false,
			'message' => '',
		];

		$commentId = $this->param(0, \diRequest::post('id', 0));

		/** @var Model $comment */
		$comment = $this->getCommentModel($commentId);

		try {
			if (!$this->userId)
			{
				throw new \Exception('Authorization required');
			}

			$target = new \diModel($this->targetId, \diTypes::getTable($this->targetType));

			if (!$target->exists())
			{
				throw new \Exception("Target {$this->targetType}#{$this->targetId} doesn't exist");
			}

			if ($this->targetType == \diTypes::user)
			{
				$ownerId = $target->getId();
			}
			elseif ($target->has('user_id'))
			{
				$ownerId = $target->get('user_id');
			}
			else
			{
				$ownerId = 0;
			}

			$comment
				->setTargetType($this->targetType)
				->setTargetId($this->targetId)
				->setUserType($this->userType)
				->setUserId($this->userId)
				->setOwnerId($ownerId)
				->setContent(\diRequest::post('content', ''))
				->setParent(\diRequest::post('parent', 0))
				->setVisible($this->Comments->moderatedBeforeShow() ? 0 : 1) // todo: use only setModerated()
				->setModerated($this->Comments->moderatedBeforeShow() ? 0 : 1)
				->save();

			// for output, sql-like format
			$comment->setDate(date('Y-m-d H:i:s'));

			$this
				->setNewComment($comment)
				->afterAddComment()
				->updateCache()
				->sendEmailNotify('add');

			$result['ok'] = true;
			$result['id'] = $comment->getId();
		} catch (\Exception $e) {
			$result['message'] = $e->getMessage();

			if ($comment->getId())
			{
				$result['ok'] = true;
				$result['id'] = $comment->getId();
			}
		}

		return $this->extendAddResult($result);
	}

	protected function extendAddResult($result)
	{
		return extend($result, [
			'html' => $this->Comments->getRowHtml($this->getNewComment()),
			'parent' => $this->getNewComment()->getParent(),
			'total_count' => $result['ok'] ? $this->Comments->incTotalCount() : $this->Comments->getTotalCount(),
			'order_num' => $this->getNewComment()->getOrderNum(),
		]);
	}

	public function editAction()
	{
		return [];
	}

	public function deleteAction()
	{
		return [];
	}

	public function refreshAction()
	{
		$where = \diRequest::post('where');
		$firstCommentId = \diRequest::post('first_comment_id', 0);
		$lastCommentId = \diRequest::post('last_comment_id', 0);
		$response = [];

		switch ($where)
		{
			case 'past':
				$response['new_comments'] = [];

				$this->Comments->setMode(\diComments::MODE_LOAD);

				$comments = $this->Comments->getPastCommentsCollection($firstCommentId);

				/** @var Model $comment */
				foreach ($comments as $comment)
				{
					$response['new_comments'][] = [
						'html' => $this->Comments->getRowHtml($comment),
						'parent' => $comment->getParent(),
						'id' => $comment->getId(),
						'order_num' => $comment->getOrderNum(),
					];
				}

				break;
		}

		return $response;
	}

	protected function response($data = [])
	{
		$this->defaultResponse(extend([
			'action' => $this->action,
			'ok' => true,
			'id' => $this->targetId,
			'type' => $this->targetType,
		], $data));
	}

	protected function checkAuth()
	{
		switch ($this->template)
		{
			case 'admin-snippet':
				/** @var \diAdminUser $admin */
				$admin = \diAdminUser::create();

				if (!$admin->authorized())
				{
					throw new \Exception('Admin auth error');
				}

				$this->userType = \diComments::utAdmin;
				$this->userId = $admin->getModel()->getId();

				break;

			case '':
				$Auth = AuthTool::create();

				/*
				if (!$Auth->authorized())
				{
					throw new Exception('User auth error');
				}
				*/

				$this->userType = \diComments::utUser;
				$this->userId = $Auth->getUserId();

				break;

			default:
				throw new \Exception("Unknown template name '$this->template'. Can't authorize");
		}

		return $this;
	}

	protected function initTpl()
	{
		switch ($this->template)
		{
			case 'admin-snippet':
				$this->initAdminTpl();
				$folder = '`_snippets/comments';
				break;

			case '':
				$this->initWebTpl();
				$folder = '~comments';
				break;

			default:
				throw new \Exception("Unknown template name '$this->template'");
		}

		$this->getTpl()
			->define($folder, [
				'comment_form',
				'comment_row',
				'comment_actions',
				'comments_block',
			]);

		return $this;
	}

	protected function emailNotifyNeeded()
	{
		return true;
	}

	/**
	 * @param $action
	 *
	 * @return null|string|array
	 */
	protected function getEmailNotifyRecipient($action)
	{
		return null;
	}

	protected function getEmailNotifySubject($action)
	{
		return null;
	}

	protected function getEmailNotifyBody($action)
	{
		return null;
	}

	protected function updateCache()
	{
		$this->Comments
			->updateCache();

		return $this;
	}

	protected function afterAddComment()
	{
		return $this;
	}

	/**
	 * @param Model $comment
	 * @param string $action
	 *
	 * @return $this
	 */
	protected function sendEmailNotify($action)
	{
		if (!$this->emailNotifyNeeded())
		{
			return $this;
		}

		$recipient = $this->getEmailNotifyRecipient($action);
		$subject = $this->getEmailNotifySubject($action);
		$body = $this->getEmailNotifyBody($action);

		if ($recipient && ($subject || $body))
		{
			Queue::basicCreate()->addAndMayBeSend([
					'email' => \diConfiguration::get('noreply_email'),
				],
				$recipient,
				$subject,
				$body
			);
		}

		return $this;
	}

	protected function getTargetCommentOwner()
	{
		if (!$this->targetCommentOwner)
		{
			$this->targetCommentOwner = \diModel::create(\diTypes::user, $this->getTargetComment()->getUserId());
		}

		return $this->targetCommentOwner;
	}

	protected function getCommenter()
	{
		if (!$this->commenter)
		{                                      // todo: this could be admin
			$this->commenter = \diModel::create(\diTypes::user, $this->getNewComment()->getUserId());
		}

		return $this->commenter;
	}

	protected function getTarget()
	{
		if (!$this->target)
		{
			$this->target = $this->getNewComment()->getTargetModel();
		}

		return $this->target;
	}

	protected function getTargetComment()
	{
		if (!$this->targetComment)
		{
			$this->targetComment = \diModel::create(\diTypes::comment, $this->getNewComment()->getParent());
		}

		return $this->targetComment;
	}

	protected function getNewComment()
	{
		if (!$this->newComment)
		{
			throw new \Exception('New comment not added');
		}

		return $this->newComment;
	}

	protected function setNewComment(Model $comment)
	{
		$this->newComment = $comment;

		return $this;
	}

	protected function getCommentModel($id)
	{
		/** @var Model $comment */
		$comment = \diModel::create(\diTypes::comment, $id);

		if ($comment->exists() && $comment->isUserAllowed(AuthTool::i()->getUserModel()))
		{
			throw new \Exception('You have no access to this comment');
		}

		return $comment;
	}
}