<?php

use Vaalyn\ShopifyLexofficeTransactions\Processor\ShopifyPayoutsProcessor;
use Vaalyn\ShopifyLexofficeTransactions\Processor\ShopifyTransactionsProcessor;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdfToTextBinaryPath = '/opt/homebrew/bin/pdftotext';

try {
	(new ShopifyTransactionsProcessor($pdfToTextBinaryPath))->process();
	(new ShopifyPayoutsProcessor())->process();
} catch (Throwable $exception) {
	print_r($exception);
}
