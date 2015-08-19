<?php
require_once 'vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse as Response;
use Windwalker\Registry\Registry;
use League\Route\RouteCollection;
//use Symfony\Component\HttpFoundation\RedirectResponse as Redirect;

$router = new RouteCollection();
$request = Request::createFromGlobals();

$registry = new Registry();
$registry->set('config.posibile-hoteluri', ['perla', 'parc']);
$registry->set('config.string_hotel_identifier_limit', 3);

/**
 * Helpers
 */
$registry->set('helper.generator-hotel-item-body', function ($identifier) use ($registry) {
    return [
        'identifier' => $identifier,
        'name' => 'Hotel '  . ucwords($identifier) . ' ' . str_repeat('*', strlen($identifier)),
        'mobile' => '0421.000.000',
        'country' => 'Romania',
        'geo' => [
            'lat' => '41.222',
            'lon' => '26.222'
        ],
        '@self' => '/hotel/' . $identifier,
        '@rooms' => '/rooms?hotel=' . $identifier,
    ];
});

$registry->set('helper.generator-room-number', function ($args) use ($registry) {
    $charLimit = $registry->get('config.string_hotel_identifier_limit');
    $prefix = strtoupper(substr(md5($args['hotelIdentifier']), 0, $charLimit));
    $padding = str_pad($args['number'], 5, 0);

    return $prefix . $padding . $args['number'];
});

$registry->set('helper.generator-room-item-body', function ($roomIdentifier, $hotelIdentifier) use ($registry) {
    $charLimit = $registry->get('config.string_hotel_identifier_limit');

    return [
        'identifier' => $roomIdentifier,
        'name' => 'Room '  . substr($roomIdentifier, $charLimit + 1),
        '@self' => '/room/' . $roomIdentifier,
        '@hotel' => '/hotel/' . $hotelIdentifier,
    ];
});


$registry->set('helper.find-hotel-by-room-identifier', function ($roomIdentifier) use ($registry) {
    $demoList = $registry->get('config.posibile-hoteluri');

    /** @var Closure $roomNumberGenerator */
    $roomNumberGenerator = $registry->get('helper.generator-room-number');

    $roomIdentifier = strtoupper($roomIdentifier);

    foreach ($demoList as $hotelIdentifier) {
        $roomsPerHotel = strlen($hotelIdentifier);

        for ($i = 1; $i <= $roomsPerHotel; $i++) {
            $generatedName = $roomNumberGenerator(['hotelIdentifier' => $hotelIdentifier, 'number' => $i]);
            if ($roomIdentifier === $generatedName) {
                return $hotelIdentifier;
            }
        }
    }
});

/**
 * Routes
 */
$router->addRoute('GET', '/hotels', function (Request $request, $response, $args) use ($registry) {
    $demoList = $registry->get('config.posibile-hoteluri');

    /** @var Closure $itemGenerator */
    $itemGenerator = $registry->get('helper.generator-hotel-item-body');

    $items = [];

    foreach ($demoList as $identifier) {
        $items[] = $itemGenerator($identifier);
    }

    return new Response($items, 200);
});

$router->addRoute('GET', '/hotel/{identifier}', function (Request $request, $response, $args) use ($registry) {
    $identifier = strtolower($args['identifier']);

    if (!in_array($identifier, $registry->get('config.posibile-hoteluri'), false)) {
        throw new \InvalidArgumentException('The requested entry does not exist.');
    }

    /** @var Closure $itemGenerator */
    $itemGenerator = $registry->get('helper.generator-hotel-item-body');

    return new Response($itemGenerator($identifier), 200);
});

$router->addRoute('GET', '/rooms', function (Request $request, $response, $args) use ($registry) {
    $request = Request::createFromGlobals();
    $demoList = $registry->get('config.posibile-hoteluri');

    /** @var Closure $roomBodyGenerator */
    $roomBodyGenerator = $registry->get('helper.generator-room-item-body');

    /** @var Closure $roomNumberGenerator */
    $roomNumberGenerator = $registry->get('helper.generator-room-number');

    $items = [];
    $filterIdentifier = $request->query->get('hotel');

    foreach ($demoList as $counter => $identifier) {
        if (is_string($filterIdentifier) && $filterIdentifier !== $identifier) {
            continue;
        }

        $roomsPerHotel = strlen($identifier);

        for ($i = 1; $i <= $roomsPerHotel; $i++) {
            $roomIdentifier = $roomNumberGenerator(['hotelIdentifier' => $identifier, 'number' => $i]);
            $items[] = $roomBodyGenerator($roomIdentifier, $identifier);
        }
    }

    return new Response($items, 200);
});

$router->addRoute('GET', '/hotel/{identifier}/rooms', function (Request $request, $response, $args) use ($registry) {
    $demoList = $registry->get('config.posibile-hoteluri');

    /** @var Closure $itemGenerator */
    $itemGenerator = $registry->get('helper.generator-hotel-item-body');

    $items = [];

    foreach ($demoList as $identifier) {
        $items[] = $itemGenerator($identifier);
    }

    return new Response($items, 200);
});

$router->addRoute('GET', '/room/{roomIdentifier}', function (Request $request, $response, $args) use ($registry) {
    /** @var Closure $roomBodyGenerator */
    $roomBodyGenerator = $registry->get('helper.generator-room-item-body');

    /** @var Closure $findItem */
    $findItem = $registry->get('helper.find-hotel-by-room-identifier');

    $roomIdentifier = $args['roomIdentifier'];
    $hotelIdentifier = $findItem($roomIdentifier);

    if ($hotelIdentifier !== null) {
        return new Response($roomBodyGenerator($roomIdentifier, $hotelIdentifier), 200);
    }

    throw new InvalidArgumentException('Room not found by identifier ' . $roomIdentifier);
});

$router->addRoute('POST', '/hotels', function (Request $request, $response, $args) use ($registry) {
    // @todo add validation

    return new Response([
        '@href' => '/hotel/ABC0000011'
    ], 201);
});

$router->addRoute('POST', '/rooms', function (Request $request, $response, $args) use ($registry) {
    // @todo add validation

    return new Response([
        '@href' => '/rooms/ABC0000044'
    ], 201);
});

$router->addRoute('DELETE', '/hotel/{identifier}', function (Request $request, $response, $args) use ($registry) {
    // @todo add validation
    return new Response(true, 200);
});

$router->addRoute('DELETE', '/room/{identifier}', function (Request $request, $response, $args) use ($registry) {
    // @todo add validation
    return new Response(true, 200);
});

$router->addRoute('PUT', '/hotel/{identifier}', function (Request $request, $response, $args) use ($registry) {
    // @todo add validation
    throw new \Exception('Method not yet implemented.');
});

$router->addRoute('PUT', '/room/{identifier}', function (Request $request, $response, $args) use ($registry) {
    // @todo add validation
    throw new \Exception('Method not yet implemented.');
});

$errorMessage = '';

try {
    $response = $router->getDispatcher()->dispatch($request->getMethod(), $request->getPathInfo());
    $response->send();

} catch (\League\Route\Http\Exception\NotFoundException $exception) {
    $response = new Response(['error' => 'Invalid call. Use GET /hotels to get started.'], 404);

} catch (\Exception $e) {
    $response = new Response(['error' => $e->getMessage()], 400);
}

$response->send();
