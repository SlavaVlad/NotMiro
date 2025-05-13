<?php

declare(strict_types=1);

return [
	'routes' => [
		// Page routes
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		
		// Mindmap API routes
		['name' => 'mindmap#save', 'url' => '/api/mindmap/save', 'verb' => 'POST'],
		['name' => 'mindmap#load', 'url' => '/api/mindmap/load', 'verb' => 'GET'],
		['name' => 'mindmap#list', 'url' => '/api/mindmap/list', 'verb' => 'GET'],
		['name' => 'mindmap#delete', 'url' => '/api/mindmap/delete', 'verb' => 'DELETE'],
	],
]; 