<?php

use Vaalyn\ShopifyLexofficeTransactions\Processor\ShopifyTransactionsProcessor;

require dirname(__DIR__) . '/vendor/autoload.php';

$pdfToTextBinaryPath = '/opt/homebrew/bin/pdftotext';

try {
	$shopifyTransactionsProcessor = new ShopifyTransactionsProcessor($pdfToTextBinaryPath);
	$shopifyTransactionsProcessor->process();
} catch (Throwable $exception) {
	print_r($exception);
}
