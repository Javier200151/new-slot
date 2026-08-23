<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'nick' =>
                $this->nick,

            'tagname' =>
                $this->tagname,

            'status' => [
                'id' =>
                    $this->status?->id,

                'name' =>
                    $this->status?->name,
            ],

            'promo' =>
                $this->promo
                    ? [
                        'id' =>
                            $this->promo->id,

                        'name' =>
                            $this->promo->name,
                    ]
                    : null,

            'member_at' =>
                $this->member_at?->format(
                    'Y-m-d'
                ),

            'metopas' =>
                $this->metopas
                    ->map(
                        fn ($metopa): array => [
                            'id' =>
                                $metopa->id,

                            'name' =>
                                $metopa->name,

                            'assigned_at' =>
                                $metopa->pivot
                                    ?->assigned_at,
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }
}