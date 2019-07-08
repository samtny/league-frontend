<?php

namespace App\Http\Resources;

use App\Http\Resources\Association as AssociationResource;
use App\Http\Resources\AssociationCollection as AssociationCollectionResource;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AssociationCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection,
            'links' => [
                'self' => 'link-value',
            ],
        ];
    }
}
