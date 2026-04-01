<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your tool. These routes
| are loaded by the ServiceProvider of your tool. You're free to add
| as many additional routes to this file as your tool may require.
|
*/

Route::post('/endpoint', function (Request $request) {
    // Replicate the model
    $model = $request->model::where('id', $request->id)->first();

    if (!$model) {
        return [
            'status' => 404,
            'message' => 'No model found.',
            'destination' => config('nova.url') . config('nova.path') . '/resources/' . $request->resource . '/'
        ];
    }

    $newModel = $model->replicate($request->except);

    if (is_array($request->override)) {
        foreach ($request->override as $field => $value) {
            $newModel->{$field} = $value;
        }
    }

    $newModel->push();

    if (isset($request->relations) && !empty($request->relations)) {
        // load the relations
        $model->load($request->relations);

        foreach ($model->getRelations() as $relation => $items) {
            $relationInstance = $newModel->{$relation}();

            if ($relationInstance instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany) {
                // sync existing records without duplicating them
                $newModel->{$relation}()->sync($items->pluck('id')->toArray());
            } else {
                // works for hasMany: duplicate the records
                foreach ($items as $item) {
                    unset($item->id);
                    $item->setAppends([]);
                    $newModel->{$relation}()->create($item->toArray());
                }
            }
        }
    }
    
    if (isset($request->media) && is_array($request->media)) {

        foreach ($request->media as $collection) {
            $medias = $model->getMedia($collection);
            foreach ($medias as $media) {
                $newModel
                    ->addMedia($media->getPath())
                    ->preservingOriginal()
                    ->toMediaCollection($collection);
            }
        }

    }
    
    // return response and redirect.
    return [
        'status' => 200,
        'message' => 'Done',
        'destination' => url(config('nova.path') . '/resources/' . $request->resource . '/' . $newModel->id)
    ];
});
