<?php
$config['rabbitmq']['host'] = '192.168.56.11';
$config['rabbitmq']['port'] = 5672;
$config['rabbitmq']['user'] = 'guest';
$config['rabbitmq']['pass'] = 'guest';
$config['rabbitmq']['vhost'] = '/';

$config['rabbitmq']['exchange'] = 'crawl';
$config['rabbitmq']['exchangeType'] = AMQP_EX_TYPE_DIRECT;