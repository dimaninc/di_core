<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.09.2016
 * Time: 11:02
 */

namespace diCore\Payment\Mixplat;

use diCore\Tool\SimpleContainer;

class ResultStatus extends SimpleContainer
{
	const PENDING = 1;
	const SUCCESS = 2;
	const NO_FUNDS = 4;
	const INTERNAL_ERROR = 5;
	const CANCELLED_BY_CUSTOMER = 6;
	const CANCELLED_BY_VENDOR = 7;
	const RETURNED_TO_CUSTOMER = 8;
	const PROCESSED_TO_OPERATOR = 9;
	const AWAITING_FOR_INIT = 10;
	const CONFIRM_OF_INIT_NEEDED = 11;
	const NO_RESPONSE_FROM_OPSOS = 12;
	const LIMIT_OF_PAYMENTS_PER_DAY = 13;
	const LIMIT_OF_AMOUNT_PER_DAY = 14;
	const LIMIT_OF_AMOUNT_PER_WEEK = 15;
	const LIMIT_OF_MIN_BALANCE = 16;
	const PREV_PAYMENT_NOT_FINISHED = 17;
	const SERVICE_UNAVAILABLE = 18;
	const TIMEOUT_EXCEEDED = 19;
	const OTHER_LIMITS_EXCEEDED = 20;
	const NOT_PAID_OTHER_REASON = 21;
	const LOW_SUM_OF_PAYMENTS = 22;
	const REDIRECTED_TO_PAY_PER_CLICK = 23;
	const OPSOS_TIMEOUT_EXCEEDED = 25;
	const OPSOS_NOT_SUPPORTED = -1;

	public static $names = [
		self::PENDING => 'pending',
		self::SUCCESS => 'success',
		self::NO_FUNDS => 'no_funds',
		self::INTERNAL_ERROR => 'internal_error',
		self::CANCELLED_BY_CUSTOMER => 'cancelled_by_customer',
		self::CANCELLED_BY_VENDOR => 'cancelled_by_vendor',
		self::RETURNED_TO_CUSTOMER => 'returned_to_customer',
		self::PROCESSED_TO_OPERATOR => 'processed_to_operator',
		self::AWAITING_FOR_INIT => 'awaiting_for_init',
		self::CONFIRM_OF_INIT_NEEDED => 'confirm_of_init_needed',
		self::NO_RESPONSE_FROM_OPSOS => 'no_response_from_opsos',
		self::LIMIT_OF_PAYMENTS_PER_DAY => 'limit_of_payments_per_day',
		self::LIMIT_OF_AMOUNT_PER_DAY => 'limit_of_amount_per_day',
		self::LIMIT_OF_AMOUNT_PER_WEEK => 'limit_of_amount_per_week',
		self::LIMIT_OF_MIN_BALANCE => 'limit_of_min_balance',
		self::PREV_PAYMENT_NOT_FINISHED => 'prev_payment_not_finished',
		self::SERVICE_UNAVAILABLE => 'service_unavailable',
		self::TIMEOUT_EXCEEDED => 'timeout_exceeded',
		self::OTHER_LIMITS_EXCEEDED => 'other_limits_exceeded',
		self::NOT_PAID_OTHER_REASON => 'not_paid_other_reason',
		self::LOW_SUM_OF_PAYMENTS => 'low_sum_of_payments',
		self::REDIRECTED_TO_PAY_PER_CLICK => 'redirected_to_pay_per_click',
		self::OPSOS_TIMEOUT_EXCEEDED => 'opsos_timeout_exceeded',
		self::OPSOS_NOT_SUPPORTED => 'opsos_not_supported',
	];

	public static $titles = [
		self::PENDING => 'Ожидает обработки',
		self::SUCCESS => 'Оплачен',
		self::NO_FUNDS => 'Ошибка, недостаточно средств',
		self::INTERNAL_ERROR => 'Внутренняя ошибка системы',
		self::CANCELLED_BY_CUSTOMER => 'Отменено покупателем',
		self::CANCELLED_BY_VENDOR => 'Отменено продавцом',
		self::RETURNED_TO_CUSTOMER => 'Возвращено покупателю',
		self::PROCESSED_TO_OPERATOR => 'Передано оператору',
		self::AWAITING_FOR_INIT => 'Ожидает отправки на инициацию',
		self::CONFIRM_OF_INIT_NEEDED => 'Необходимо подтверждение инициации',
		self::NO_RESPONSE_FROM_OPSOS => 'Нет ответа от сервера оператора',
		self::LIMIT_OF_PAYMENTS_PER_DAY => 'Лимит по количеству платежей за сутки',
		self::LIMIT_OF_AMOUNT_PER_DAY => 'Лимит по сумме оплаты за сутки',
		self::LIMIT_OF_AMOUNT_PER_WEEK => 'Лимит по сумме оплаты за неделю',
		self::LIMIT_OF_MIN_BALANCE => 'Лимит по минимальному остатку на счете',
		self::PREV_PAYMENT_NOT_FINISHED => 'Предыдущий платеж не завершен',
		self::SERVICE_UNAVAILABLE => 'Услуга недоступна для абонента',
		self::TIMEOUT_EXCEEDED => 'Время ожидания абонента истекло',
		self::OTHER_LIMITS_EXCEEDED => 'Превышены другие лимиты (недостаточно средств)',
		self::NOT_PAID_OTHER_REASON => 'Не оплачен (по другой причине)',
		self::LOW_SUM_OF_PAYMENTS => 'Сумма платежа меньше допустимой',
		self::REDIRECTED_TO_PAY_PER_CLICK => 'Платеж перенаправлен на Pay-Per-Click',
		self::OPSOS_TIMEOUT_EXCEEDED => 'Превышено время ожидания статуса платежа от оператора. Платёж неуспешен',
		self::OPSOS_NOT_SUPPORTED => 'Платеж невозможен для Вашего оператора связи',
	];
}