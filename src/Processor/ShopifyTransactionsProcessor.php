<?php

declare(strict_types=1);

namespace Vaalyn\ShopifyLexofficeTransactions\Processor;

use DateTime;
use League\Csv\Writer;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ShopifyTransactionsProcessor
{
    protected const TRANSACTIONS_PATH = __DIR__ . '/../../var/transactions/';

    public function __construct(
        protected string $pdfToTextBinaryPath
    ) {
    }

    public function process(): void
    {
        $lexofficeTransactionRecords = [];

        foreach ($this->fetchTransactionPdfFiles() as $pdfFile) {
            $pdfFileContents = $this->readPdfFile($pdfFile->getPathname());

            /** @var null|string $totalAmount */
            $totalAmount = null;

            /** @var null|string $descriptionDate */
            $descriptionDate = null;

            /** @var null|DateTime $invoiceDate */
            $invoiceDate = null;

            foreach($this->readPdfLines($pdfFileContents) as $pdfLine) {
                if (str_starts_with($pdfLine, 'Amount (EUR) ')) {
                    $amountsString = str_replace('Amount (EUR) ', '', $pdfLine);
                    $amounts = explode(' ', $amountsString);

                    $totalAmount = $amounts[array_key_last($amounts)];
                    continue;
                }

                if (str_starts_with($pdfLine, 'Shopify Payments Invoice for ')) {
                    $descriptionDate = (string) str_replace('Shopify Payments Invoice for ', '', $pdfLine);
                    continue;
                }

                if (str_starts_with($pdfLine, 'Invoice Reference #')) {
                    $invoiceDateString = explode(' - ', $pdfLine)[1] ?? '';
                    $invoiceDateString = str_replace('Issued ', '', $invoiceDateString);

                    $invoiceDate = DateTime::createFromFormat('F j, Y', $invoiceDateString);
                    continue;
                }
            }

            if (
                empty($totalAmount)
                || empty($descriptionDate)
                || empty($invoiceDate)
            ) {
                echo sprintf('ERROR: Could not find all data for file "%s"', $pdfFile->getPathname()) . PHP_EOL;
                print_r([
                    '$totalAmount' => $totalAmount,
                    '$descriptionDate' => $descriptionDate,
                    '$invoiceDate' => $invoiceDate,
                ]);
                continue;
            }

            $lexofficeTransactionRecords[] = $this->buildLexofficeTransaction(
                $totalAmount,
                $descriptionDate,
                $invoiceDate
            );

            $this->moveTransactionPdfToArchive($pdfFile);
        }

        $this->createCsvForLexofficeTransactions($lexofficeTransactionRecords);
    }

    /**
     * @return SplFileInfo[]
     */
    protected function fetchTransactionPdfFiles(): iterable
    {
        return (new Finder())->files()
            ->in(self::TRANSACTIONS_PATH . 'new')
            ->name('*.pdf')
            ->getIterator();
    }

    protected function readPdfFile(string $filePath): string
    {
        return Pdf::getText($filePath, $this->pdfToTextBinaryPath);
    }

    /**
     * @return string[]
     */
    protected function readPdfLines(string $pdfFileContents): iterable
    {
        $pdfLine = strtok($pdfFileContents, PHP_EOL);
        while($pdfLine !== false) {
            yield $pdfLine;

            $pdfLine = strtok(PHP_EOL);
        }
    }

    /**
     * @return string[]
     */
    protected function buildLexofficeTransaction(
        string $amount,
        string $description,
        DateTime $invoiceDate
    ): array {
        return [
            '-' . $amount,
            'Shopify Payments',
            $description,
            $invoiceDate->format('d.m.Y'),
        ];
    }

    /**
     * @param array<string[]> $transactions
     */
    protected function createCsvForLexofficeTransactions(array $transactions): void
    {
        $fileName = date('Y-m-d') . '-shopify-transactions.csv';

        touch(self::TRANSACTIONS_PATH . $fileName);
        $csvWriter = Writer::createFromPath(self::TRANSACTIONS_PATH . $fileName, 'rw+');

        $csvWriter->insertOne([
            'Betrag',
            'Auftraggeber / Empfänger',
            'Verwendungszweck',
            'Datum',
        ]);

        $csvWriter->insertAll($transactions);
    }

    protected function moveTransactionPdfToArchive(SplFileInfo $pdfFile): void
    {
        $archivePath = self::TRANSACTIONS_PATH . 'processed/';

        rename(
            $pdfFile->getPathname(),
            $archivePath . $pdfFile->getFilename()
        );
    }
}
