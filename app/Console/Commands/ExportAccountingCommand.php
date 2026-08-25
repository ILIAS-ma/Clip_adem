<?php

namespace App\Console\Commands;

use App\Services\Accounting\AccountingExport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExportAccountingCommand extends Command
{
    protected $signature = 'accounting:export
        {journal=depenses : depenses | versements}
        {--from= : Date de début, format AAAA-MM-JJ}
        {--to= : Date de fin, format AAAA-MM-JJ}
        {--path= : Chemin du fichier de sortie}';

    protected $description = 'Exporte un journal comptable au format CSV.';

    public function handle(AccountingExport $export): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null;

        try {
            $result = $export->toFile($this->argument('journal'), $this->option('path'), $from, $to);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$result['rows']} ligne(s) exportée(s) vers {$result['path']}");

        $reconciliation = $export->reconciliation();

        $this->newLine();
        $this->line('Contrôle de cohérence :');
        $this->table(
            ['Poste', 'Montant'],
            [
                ['Budget consommé (grand livre)', $this->euros($reconciliation['spent_cents'])],
                ['Versé aux clippeurs', $this->euros($reconciliation['paid_cents'])],
                ['Dû aux clippeurs', $this->euros($reconciliation['owed_cents'])],
            ],
        );

        return self::SUCCESS;
    }

    protected function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
