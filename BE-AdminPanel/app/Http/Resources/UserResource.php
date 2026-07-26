<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
          'id' => $this->id,
          'name' => $this->name,
          'email' => $this->email,
          'tel_number' => $this->tel_number,
          'avatar' => $this->avatar,
          'isAdmin' => $this->isAdmin(),
          'role' => (int) $this->role,
          'isDriver' => (int) $this->role === 2,
          'emailVerified' => $this->email_verified_at,
        ];
    }
}
