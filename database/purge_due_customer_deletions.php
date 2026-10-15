<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('BULBULA_ROOT',dirname(__DIR__));
require BULBULA_ROOT.'/vendor/autoload.php';require BULBULA_ROOT.'/src/Core/Autoloader.php';
\Bulbula\Core\Autoloader::register(BULBULA_ROOT.'/src');
use Bulbula\Core\Config;use Bulbula\Core\Database;use Bulbula\Repositories\CustomerRepository;use Bulbula\Services\CustomerLifecycleMailer;
Config::load(BULBULA_ROOT.'/config/env.json');$repo=new CustomerRepository();$mailer=new CustomerLifecycleMailer();$ids=$repo->listDueDeletionIds();foreach($ids as $id){$row=Database::fetchOne("SELECT email FROM customers WHERE id=:id AND account_status='PENDING_DELETION'",[':id'=>$id]);if($row===null)continue;$email=(string)$row['email'];$repo->permanentlyErasePendingDeletion($id);$mailer->deleted($email);}fwrite(STDOUT,'Processed '.count($ids)." due Customer deletion(s).\n");
