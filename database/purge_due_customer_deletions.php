<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('BULBULA_ROOT',dirname(__DIR__));
require BULBULA_ROOT.'/vendor/autoload.php';
use Bulbula\Core\Config;use Bulbula\Repositories\CustomerRepository;
Config::load(BULBULA_ROOT.'/config');$repo=new CustomerRepository();$ids=$repo->listDueDeletionIds();foreach($ids as $id)$repo->permanentlyErasePendingDeletion($id);fwrite(STDOUT,'Processed '.count($ids)." due Customer deletion(s).\n");
