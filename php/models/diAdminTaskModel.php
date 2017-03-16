<?php
/**
 * Created by diModelsManager
 * Date: 11.09.2015
 * Time: 11:29
 */
/**
 * Class diAdminTaskModel
 * Methods list for IDE
 *
 * @method string	getTitle
 * @method string	getContent
 * @method integer	getVisible
 * @method integer	getStatus
 * @method integer	getPriority
 * @method string	getDueDate
 * @method string	getDate
 * @method integer	getAdminId
 *
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasVisible
 * @method bool hasStatus
 * @method bool hasPriority
 * @method bool hasDueDate
 * @method bool hasDate
 * @method bool hasAdminId
 *
 * @method diAdminTaskModel setTitle($value)
 * @method diAdminTaskModel setContent($value)
 * @method diAdminTaskModel setVisible($value)
 * @method diAdminTaskModel setStatus($value)
 * @method diAdminTaskModel setPriority($value)
 * @method diAdminTaskModel setDueDate($value)
 * @method diAdminTaskModel setDate($value)
 * @method diAdminTaskModel setAdminId($value)
 */
class diAdminTaskModel extends diModel
{
	const type = diTypes::admin_task;
	protected $table = "admin_tasks";

	public function getHref()
	{
		return $this->getAdminHref();
	}

	public function getCustomTemplateVars()
	{
		$contentHtml = nl2br(diStringHelper::out($this->getContent()));
		$contentHtmlWithLinks = nl2br(highlight_urls(diStringHelper::out($this->getContent())));

		return extend(parent::getCustomTemplateVars(), [
			"content_html" => $contentHtml,
			"content_html_with_links" => $contentHtmlWithLinks,
		]);
	}
}