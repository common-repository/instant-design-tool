<?php

namespace IDT\Core\Helpers\Exceptions;

use Exception;
use IDT\Admin\Templates\IDT_Flash_Notice_Handler;

class QuoteReachedException extends Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
        update_option('IDT_pdf_request_quota_reached', true);
    }
}