<?php

namespace diCore\Push;

/** Итог отправки. Временная ошибка сервиса – не итог, а TransportError: её повторяют. */
enum Delivery
{
    case Delivered;
    /** Адрес больше не действует (приложение удалено, подписка отозвана) – запись удаляют. */
    case Gone;
}
