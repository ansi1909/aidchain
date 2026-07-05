<?php

namespace App\Command;

use App\Service\CryptoLedgerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recorre el ledger completo y verifica la integridad de la cadena de hashes.
 * Pensado para correr periódicamente (cron) como "alarma perimetral": si
 * alguien manipuló la base de datos, este comando termina con código de error.
 */
#[AsCommand(
    name: 'app:ledger:audit-chain',
    description: 'Verifica la integridad criptográfica de la cadena de eventos del ledger.',
)]
class AuditChainCommand extends Command
{
    public function __construct(
        private readonly CryptoLedgerService $ledgerService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Auditoría de integridad del ledger');

        $resultado = $this->ledgerService->verifyChain();

        $io->text(sprintf('Bloques analizados: %d', $resultado['total']));

        if ($resultado['valid']) {
            $io->success('La cadena está íntegra. No se detectaron manipulaciones.');

            return Command::SUCCESS;
        }

        $io->error(sprintf('Se detectaron %d ruptura(s) en la cadena.', \count($resultado['breaks'])));

        $io->table(
            ['Bloque (id)', 'Tipo', 'Detalle'],
            array_map(
                static fn (array $break): array => [
                    (string) ($break['id'] ?? '—'),
                    $break['tipo'],
                    $break['detalle'],
                ],
                $resultado['breaks'],
            ),
        );

        return Command::FAILURE;
    }
}
