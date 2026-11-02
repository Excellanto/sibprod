<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientModelReportService
{
    public const HEADINGS = [
        'ClientId',
        'ModelNo',
        'RefNumber',
        'LiningName',
        'SpecialDescriptionLine1',
        'SpecialDescriptionLine2',
        'LoopBandSize',
        'EndWidth',
        'BuckleType',
        'BuckleWidth',
        'DesignName',
        'DesignDescription',
        'VarnishColour',
        'TopThreadColour',
        'BottomThreadColour',
        'TopThreadSize',
        'BottomThreadSize',
        'StampingShort',
        'StampingLong',
        'PictureName',
        'NoteInfoLine1',
        'NoteInfoLine2',
        'LengthShortPart',
        'LengthLongPart',
        'FirstCuttingTool',
        'InsideCuttingTool',
        'LoopCuttingTool',
        'LoopFoldingTool',
        'BigLoop',
        'SmallLoop',
    ];

    public function listForClient(int $clientId): Collection
    {
        return ClientModel::query()
            ->select([
                'clientmodel.clientmodelId',
                'clientmodel.created_at',
                'clients.client_name',
            ])
            ->leftJoin('clients', 'clients.id', '=', 'clientmodel.ClientId')
            ->where('clientmodel.ClientId', $clientId)
            ->orderByDesc('clientmodel.clientmodelId')
            ->get()
            ->map(fn ($row) => [
                'clientmodelId' => $row->clientmodelId,
                'client_name' => $row->client_name ?? '-',
                'created_at' => $row->created_at?->format('d/m/Y H:i') ?? '-',
            ]);
    }

    /**
     * Full client report: all models with all export columns (paginated for UI).
     */
    public function paginatedReportForClient(int $clientId, int $perPage = 50): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = max(10, min($perPage, 200));

        $paginator = $this->baseQuery()
            ->where('cm.ClientId', $clientId)
            ->orderBy('cm.clientmodelId')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(function ($row) {
                return array_merge(
                    ['clientmodelId' => $row->clientmodelId],
                    $this->mapExportRow($row)
                );
            })
        );

        return $paginator;
    }

    public function reportCountForClient(int $clientId): int
    {
        return (int) DB::table('clientmodel')
            ->where('ClientId', $clientId)
            ->count();
    }

    /**
     * Clients that have at least one model, with model counts (for bulk report generation).
     *
     * @return Collection<int, object{id: int, client_name: string, model_count: int}>
     */
    public function clientsWithModelCounts(): Collection
    {
        return DB::table('clientmodel as cm')
            ->join('clients as c', 'c.id', '=', 'cm.ClientId')
            ->select([
                'c.id',
                'c.client_name',
                DB::raw('COUNT(*) as model_count'),
            ])
            ->groupBy('c.id', 'c.client_name')
            ->orderBy('c.client_name')
            ->get();
    }

    public function reportCountAllClients(): int
    {
        return (int) DB::table('clientmodel')->count();
    }

    public function rowsForAllClients(): Collection
    {
        return $this->rowsForAllClientsQuery()
            ->get()
            ->map(fn ($row) => $this->mapExportRow($row));
    }

    public function rowsForAllClientsQuery(): Builder
    {
        return $this->baseQuery()
            ->orderBy('c.client_name')
            ->orderBy('cm.clientmodelId');
    }

    public function rowsForClient(int $clientId): Collection
    {
        return $this->baseQuery()
            ->where('cm.ClientId', $clientId)
            ->orderBy('cm.clientmodelId')
            ->get()
            ->map(fn ($row) => $this->mapExportRow($row));
    }

    public function rowsForClientQuery(int $clientId): Builder
    {
        return $this->baseQuery()
            ->where('cm.ClientId', $clientId)
            ->orderBy('cm.clientmodelId');
    }

    public function getRow(int $clientmodelId): ?array
    {
        $row = $this->baseQuery()
            ->where('cm.clientmodelId', $clientmodelId)
            ->first();

        if (! $row) {
            return null;
        }

        return $this->mapExportRow($row);
    }

    public function getRowWithMeta(int $clientmodelId): ?array
    {
        $row = $this->baseQuery()
            ->addSelect('clients.client_name')
            ->where('cm.clientmodelId', $clientmodelId)
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'client_name' => $row->client_name ?? '-',
            'fields' => $this->mapExportRow($row),
        ];
    }

    public function mapExportRow(object $row): array
    {
        $mapped = [];
        foreach (self::HEADINGS as $heading) {
            $mapped[$heading] = $this->formatCell($row->{$heading} ?? null, $heading);
        }

        return $mapped;
    }

    public function formatCell(mixed $value, string $column): string|int
    {
        if (in_array($column, ['BigLoop', 'SmallLoop'], true)) {
            return (int) (bool) $value;
        }

        if ($column === 'ClientId') {
            return $value !== null && $value !== '' ? (int) $value : 0;
        }

        if ($value === null || $value === '') {
            return $column === 'BuckleType' ? '----' : '---';
        }

        return (string) $value;
    }

    protected function baseQuery(): Builder
    {
        return DB::table('clientmodel as cm')
            ->leftJoin('clients as c', 'c.id', '=', 'cm.ClientId')
            ->leftJoin('linings as lining', 'lining.LiningId', '=', 'cm.LiningId')
            ->leftJoin('special as special1', 'special1.SpecialId', '=', 'cm.SpecialId1')
            ->leftJoin('special as special2', 'special2.SpecialId', '=', 'cm.SpecialId2')
            ->leftJoin('loopband', 'loopband.LoopBandId', '=', 'cm.LoopBandId')
            ->leftJoin('endwidth', 'endwidth.EndWidthId', '=', 'cm.EndWidthId')
            ->leftJoin('buckle', 'buckle.BuckleId', '=', 'cm.BuckleId')
            ->leftJoin('bucklewidth', 'bucklewidth.BuckleWidthId', '=', 'cm.BuckleWidthId')
            ->leftJoin('designname', 'designname.DesignNameId', '=', 'cm.DesignNameId')
            ->leftJoin('designdescription', 'designdescription.DesignDescriptionId', '=', 'cm.DesignDescriptionId')
            ->leftJoin('colour as varnish_colour', 'varnish_colour.ColourId', '=', 'cm.VarnishColourId')
            ->leftJoin('colour as top_thread_colour', 'top_thread_colour.ColourId', '=', 'cm.TopThreadColourId')
            ->leftJoin('colour as bottom_thread_colour', 'bottom_thread_colour.ColourId', '=', 'cm.BottomThreadColourId')
            ->leftJoin('threadsize as top_thread_size', 'top_thread_size.ThreadSizeId', '=', 'cm.TopThreadSizeId')
            ->leftJoin('threadsize as bottom_thread_size', 'bottom_thread_size.ThreadSizeId', '=', 'cm.BottomThreadSizeId')
            ->leftJoin('stampingshort', 'stampingshort.StampingShortId', '=', 'cm.StampingShortId')
            ->leftJoin('stampinglong', 'stampinglong.StampingLongId', '=', 'cm.StampingLongId')
            ->leftJoin('strappictures', 'strappictures.StrapPictureId', '=', 'cm.StrapPictureId')
            ->leftJoin('noteinfoline1', 'noteinfoline1.NoteInfoLine1Id', '=', 'cm.NoteInfoLine1Id')
            ->leftJoin('noteinfoline2', 'noteinfoline2.NoteInfoLine2Id', '=', 'cm.NoteInfoLine2Id')
            ->leftJoin('straplengthshortpart', 'straplengthshortpart.StrapLengthShortPartId', '=', 'cm.StrapLengthShortPartId')
            ->leftJoin('straplengthlongpart', 'straplengthlongpart.StrapLengthLongPartId', '=', 'cm.StrapLengthLongPartId')
            ->leftJoin('tools as first_tool', 'first_tool.ToolId', '=', 'cm.FirstCuttingToolId')
            ->leftJoin('tools as inside_tool', 'inside_tool.ToolId', '=', 'cm.InsideCuttingToolId')
            ->leftJoin('tools as loop_cut_tool', 'loop_cut_tool.ToolId', '=', 'cm.LoopCuttingToolId')
            ->leftJoin('tools as loop_fold_tool', 'loop_fold_tool.ToolId', '=', 'cm.LoopFoldingToolId')
            ->select([
                'cm.clientmodelId',
                'cm.ClientId',
                'cm.ModelNo',
                'cm.RefNumber',
                'lining.Name as LiningName',
                'special1.Description as SpecialDescriptionLine1',
                'special2.Description as SpecialDescriptionLine2',
                'loopband.LoopBandSize',
                'endwidth.EndWidthSize as EndWidth',
                'buckle.BuckleType',
                'bucklewidth.BuckleWidthSize as BuckleWidth',
                'designname.Name as DesignName',
                'designdescription.Description as DesignDescription',
                'varnish_colour.ColourName as VarnishColour',
                'top_thread_colour.ColourName as TopThreadColour',
                'bottom_thread_colour.ColourName as BottomThreadColour',
                'top_thread_size.ThreadSize as TopThreadSize',
                'bottom_thread_size.ThreadSize as BottomThreadSize',
                'stampingshort.Description as StampingShort',
                'stampinglong.Description as StampingLong',
                'strappictures.Name as PictureName',
                'noteinfoline1.Line1 as NoteInfoLine1',
                'noteinfoline2.Line2 as NoteInfoLine2',
                'straplengthshortpart.ShortPart as LengthShortPart',
                'straplengthlongpart.LongPart as LengthLongPart',
                'first_tool.Name as FirstCuttingTool',
                'inside_tool.Name as InsideCuttingTool',
                'loop_cut_tool.Name as LoopCuttingTool',
                'loop_fold_tool.Name as LoopFoldingTool',
                'cm.BigLoop',
                'cm.SmallLoop',
                'c.client_name',
            ]);
    }

    public function findClient(int $clientId): ?Client
    {
        return Client::query()->find($clientId);
    }
}
