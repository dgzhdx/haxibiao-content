<?php

declare (strict_types = 1);

use Haxibiao\Content\Controllers\CategoryController;
use Illuminate\Contracts\Routing\Registrar as RouteRegisterContract;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'category'], function (RouteRegisterContract $route) {
    //管理专题
    Route::get('/list', CategoryController::class . '@list');
});

Route::resource('/category', CategoryController::class);

//TODO 这个里面还有梗,注意这个category的匹配顺序
//Route::get('/{name_en}', CategoryController::class.'@name_en')->where('name_en', '(?!nova).*');
