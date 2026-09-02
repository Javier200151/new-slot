<?php

namespace App\Support;

use App\Models\Operation;
use App\Models\OperationType;

class OperationTypeConfiguration
{
    private static array $typeCache = [];

    public static function find(mixed $operationTypeId): ?OperationType
    {
        if (blank($operationTypeId)) {
            return null;
        }

        $id = (int) $operationTypeId;

        if (! array_key_exists($id, self::$typeCache)) {
            self::$typeCache[$id] = OperationType::query()->find($id);
        }

        return self::$typeCache[$id];
    }

    public static function normalizeOperationData(array $data): array
    {
        $type = self::find($data['operation_type_id'] ?? null);

        if (! $type) {
            return $data;
        }

        if (! $type->supportsOcap()) {
            $data['ocap'] = false;
        }

        if (! $type->supportsRespawn()) {
            $data['respawn'] = false;
        }

        if (! $type->supportsJip()) {
            $data['jip'] = false;
        }

        if (! $type->awardsMetopa()) {
            $data['metopa_id'] = null;
        }

        return $data;
    }

    public static function normalizeEventData(array $data, ?int $operationId = null): array
    {
        $operationId ??= isset($data['operation_id'])
            ? (int) $data['operation_id']
            : null;

        if (! $operationId) {
            return $data;
        }

        $operation = Operation::query()
            ->with('operationType')
            ->find($operationId);

        if (! $operation) {
            return $data;
        }

        if (! ($operation->operationType?->usesEventResult() ?? true)) {
            $data['event_result_id'] = null;
        }

        if (
            ! ($operation->operationType?->supportsOcap() ?? true)
            || ! $operation->ocap
        ) {
            $data['ocap_url'] = null;
        }

        return $data;
    }
}
