<?php
namespace Config;
use AnserGateway\Router\RouteCollector;
use Workerman\Protocols\Http\Response;
return function (RouteCollector $route) {
    /**
     * system default route
     */
    $route->get('/',[\App\Controllers\HeartBeat::class, 'index']);
    $route->get('/test',[\App\Controllers\HeartBeat::class, 'test']);
    $route->post('/testPost',[\App\Controllers\HeartBeat::class, 'testPost']);

    /**
     * service api
     */
    $route->get('/products',[\App\Controllers\Product::class, 'products']);
    $route->post('api/v1/async/order', 'AsyncIngress::createOrder');
}

?>