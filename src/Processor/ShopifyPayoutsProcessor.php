<?php

declare(strict_types=1);

namespace Vaalyn\ShopifyLexofficeTransactions\Processor;

use DateTime;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ShopifyPayoutsProcessor
{
    protected const PAYOUTS_PATH = __DIR__ . '/../../var/payouts/';

    public function process(): void
    {
        $lexofficePayoutRecords = [];

        foreach ($this->fetchPayoutCsvFiles() as $csvFile) {
            $csvReader = Reader::createFromString($csvFile->getContents());
            $csvReader->setHeaderOffset(0);

            foreach ($csvReader->getRecords() as $row) {
                $totalAmount = number_format(
                    num: (float) $row['Total'],
                    decimals: 2,
                    decimal_separator: ',',
                    thousands_separator: ''
                );

                $payoutDate = DateTime::createFromFormat('Y-m-d', $row['Payout Date']);

                if (empty($totalAmount) || empty($payoutDate)) {
                    echo sprintf('ERROR: Could not find all data for file "%s"', $csvFile->getPathname()) . PHP_EOL;
                    print_r([
                        '$totalAmount' => $totalAmount,
                        '$payoutDate' => $payoutDate,
                    ]);
                    continue;
                }

                $lexofficePayoutRecords[] = $this->buildLexofficePayout($totalAmount, $payoutDate);
            }

            $this->movePayoutCsvToArchive($csvFile);
        }

        if (empty($lexofficePayoutRecords)) {
            return;
        }

        $this->createCsvForLexofficePayouts($lexofficePayoutRecords);
    }

    /**
     * @return SplFileInfo[]
     */
    protected function fetchPayoutCsvFiles(): iterable
    {
        return (new Finder())->files()
            ->in(self::PAYOUTS_PATH . 'new')
            ->name('*.csv')
            ->getIterator();
    }

    /**
     * @return string[]
     */
    protected function buildLexofficePayout(
        string $amount,
        DateTime $payoutDate
    ): array {
        return [
            '-' . $amount,
            'Shopify Stripe AG',
            'Sammelauszahlung',
            $payoutDate->format('d.m.Y'),
        ];
    }

    /**
     * @param array<string[]> $payouts
     */
    protected function createCsvForLexofficePayouts(array $payouts): void
    {
        $fileName = date('Y-m-d') . '-shopify-payouts.csv';

        touch(self::PAYOUTS_PATH . $fileName);
        $csvWriter = Writer::createFromPath(self::PAYOUTS_PATH . $fileName, 'rw+');

        $csvWriter->insertOne([
            'Ausgabe',
            'Auftraggeber',
            'Verwendungszweck',
            'Datum',
        ]);

        $csvWriter->insertAll($payouts);
    }

    protected function movePayoutCsvToArchive(SplFileInfo $pdfFile): void
    {
        $archivePath = self::PAYOUTS_PATH . 'processed/';

        rename(
            $pdfFile->getPathname(),
            $archivePath . $pdfFile->getFilename()
        );
    }
}
