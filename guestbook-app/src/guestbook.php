<?php

/**
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

function redis_client(string $service): Predis\Client {
  $host = $service;
  if (getenv('GET_HOSTS_FROM') == 'env') {
    $host = getenv(strtoupper(str_replace('-', '_', $service)) . '_SERVICE_HOST');
  }
  return new Predis\Client(['scheme' => 'tcp', 'host' => $host, 'port' => 6379]);
}

$cmd = $_GET['cmd'] ?? '';
switch ($cmd) {
  case 'set':
    redis_client('redis-leader')->set('guestbook', $_GET['value'] ?? '');
    print(json_encode(['message' => 'Updated']));
    break;
  case 'get':
    print(json_encode(['data' => (string) redis_client('redis-follower')->get('guestbook')]));
    break;
  case 'info':
    print(json_encode([
      'version' => getenv('APP_VERSION') ?: 'dev',
      'environment' => getenv('ENVIRONMENT') ?: 'local',
    ]));
    break;
  default:
    http_response_code(400);
    print(json_encode(['error' => 'unknown cmd']));
}
