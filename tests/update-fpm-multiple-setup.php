<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1'||getenv('TEST_UPDATE_MULTIPLE_MASTERS')!=='1'||dirname(__DIR__)!=='/tmp/search-update-fpm-source'||!is_file(dirname(__DIR__).'/storage/web-cache-test-only'))exit(1);
$path='/tmp/update-fpm-nginx.conf';$source=file_get_contents($path);
$start=strpos($source,'    server {');$end=strrpos($source,"\n}");
if($start===false||$end===false||substr_count($source,'listen 127.0.0.1:80;')!==1)throw new RuntimeException('Fresh isolated Nginx configuration required');
$second=str_replace(['127.0.0.1:80;','127.0.0.1:9000;'],['127.0.0.1:81;','127.0.0.1:9001;'],substr($source,$start,$end-$start));
file_put_contents($path,substr($source,0,$end)."\n".$second.substr($source,$end));
