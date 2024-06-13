<?php

declare(strict_types=1);

namespace Buggregator\Trap\Sender\Console\Renderer;

use Buggregator\Trap\Proto\Frame;
use Buggregator\Trap\ProtoType;
use Buggregator\Trap\Sender\Console\Renderer;
use Buggregator\Trap\Sender\Console\Support\Common;
use Buggregator\Trap\Sender\Console\Support\Tables;
use Buggregator\Trap\Support\Measure;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @implements Renderer<Frame\Profiler>
 *
 * @internal
 */
final class Profiler implements Renderer
{
    public function isSupport(Frame $frame): bool
    {
        return $frame->type === ProtoType::Profiler;
    }

    public function render(OutputInterface $output, Frame $frame): void
    {
        \assert($frame instanceof Frame\Profiler);

        $subtitle = $frame->payload->type->value;
        Common::renderHeader1($output, 'PROFILER', $subtitle);

        $profile = $frame->payload->getProfile();
        $metadata = $profile->metadata;

        $data = [];
        isset($metadata['date']) && \is_numeric($metadata['date'])
        and $data['Time'] = new \DateTimeImmutable('@' . $metadata['date']);
        isset($metadata['app_name']) and $data['App name'] = $metadata['app_name'];
        isset($metadata['hostname']) and $data['Hostname'] = $metadata['hostname'];
        isset($metadata['filename']) and $data['File name'] = $metadata['filename'] . (
            isset($metadata['filesize']) && \is_int($metadata['filesize'])
                ? ' (' . Measure::memory($metadata['filesize']) . ')'
                : ''
        );
        $data['Num edges'] = $profile->calls->count();

        Common::renderMetadata($output, $data);
        if ($profile->tags !== []) {
            $output->writeln('');
            Common::renderTags($output, $profile->tags);
            $output->writeln('');
        }

        // Render peaks
        $peaks = $profile->peaks;
        Tables::renderKeyValueTable($output, 'Peak values', [
            'Memory usage' => Measure::memory($peaks->mu),
            'Peak memory usage' => Measure::memory($peaks->pmu),
            'Wall time' => $peaks->wt,
            'CPU time' => $peaks->cpu,
            'Calls count' => $peaks->ct,
        ]);
    }
}
