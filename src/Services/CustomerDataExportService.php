<?php
declare(strict_types=1);
namespace Bulbula\Services;
use Bulbula\Repositories\AuditLogRepository;use Bulbula\Repositories\CustomerRepository;use Bulbula\Repositories\ReviewRepository;
final class CustomerDataExportService
{
 public function __construct(private readonly CustomerRepository $customers=new CustomerRepository(),private readonly ReviewRepository $reviews=new ReviewRepository(),private readonly AuditLogRepository $audit=new AuditLogRepository()){}
 public function build(array $customer): string
 {
  $id=(int)$customer['id'];$profile=[];foreach(['id','email','display_name','phone_number','preferred_language','account_status','email_verified_at','password_changed_at','last_active_surface','created_at','updated_at'] as $key)$profile[$key]=$customer[$key]??null;
  $payload=['export_version'=>1,'generated_at'=>date(DATE_ATOM),'service'=>'Bulbula.et','customer_profile'=>$profile,'saved_places'=>$this->customers->listSavedBusinesses($id),'reviews'=>$this->reviews->listByCustomer($id),'account_activity'=>$this->audit->listForCustomer($id,50),'privacy_note'=>'Authentication secrets, password hashes, OTP records, CSRF values, and session tokens are intentionally excluded.'];
  return (string)json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 }
}
