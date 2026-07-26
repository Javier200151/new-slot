<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserMetopaAssignmentService
{
    public function assign(
        int $userId,
        int $metopaId,
        CarbonInterface|string $assignedAt,
        bool $updateExisting = false,
    ): string {
        $this->validateIds($userId, $metopaId);

        return DB::transaction(function () use (
            $userId,
            $metopaId,
            $assignedAt,
            $updateExisting,
        ): string {
            $assignment = DB::table('metopa_user')
                ->where('user_id', $userId)
                ->where('metopa_id', $metopaId)
                ->lockForUpdate()
                ->first();

            if ($assignment === null) {
                DB::table('metopa_user')->insert([
                    'user_id' => $userId,
                    'metopa_id' => $metopaId,
                    'assigned_at' => $assignedAt,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);

                return 'created';
            }

            if ($assignment->deleted_at !== null) {
                DB::table('metopa_user')
                    ->where('user_id', $userId)
                    ->where('metopa_id', $metopaId)
                    ->update([
                        'assigned_at' => $assignedAt,
                        'deleted_at' => null,
                        'updated_by' => Auth::id(),
                        'updated_at' => now(),
                    ]);

                return 'restored';
            }

            if (! $updateExisting) {
                return 'already_exists';
            }

            DB::table('metopa_user')
                ->where('user_id', $userId)
                ->where('metopa_id', $metopaId)
                ->update([
                    'assigned_at' => $assignedAt,
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]);

            return 'updated';
        });
    }

    public function updateAssignedAt(
        int $userId,
        int $metopaId,
        CarbonInterface|string $assignedAt,
    ): void {
        $updated = DB::table('metopa_user')
            ->where('user_id', $userId)
            ->where('metopa_id', $metopaId)
            ->whereNull('deleted_at')
            ->update([
                'assigned_at' => $assignedAt,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'assigned_at' => 'La asignación ya no existe o fue eliminada.',
            ]);
        }
    }

    public function delete(
        int $userId,
        int $metopaId,
    ): void {
        DB::table('metopa_user')
            ->where('user_id', $userId)
            ->where('metopa_id', $metopaId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);
    }

    private function validateIds(
        int $userId,
        int $metopaId,
    ): void {
        Validator::make(
            [
                'user_id' => $userId,
                'metopa_id' => $metopaId,
            ],
            [
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'metopa_id' => ['required', 'integer', 'exists:metopas,id'],
            ],
        )->validate();
    }
}