<?php
declare(strict_types=1);
namespace Bulbula\Controllers\Staff;
use Bulbula\Core\Csrf;use Bulbula\Core\Request;use Bulbula\Core\Response;use Bulbula\Core\Session;use Bulbula\Core\View;use Bulbula\Repositories\PrivacyRequestRepository;use Bulbula\Services\AuditService;use Bulbula\Services\StaffAuthService;
final class PrivacyRequestController
{
 public function __construct(private readonly StaffAuthService $auth=new StaffAuthService(),private readonly PrivacyRequestRepository $repo=new PrivacyRequestRepository(),private readonly AuditService $audit=new AuditService()){}
 public function index(Request $request): Response { return Response::html(View::render('staff/privacy-requests',['pageTitle'=>'Privacy Requests','activeNav'=>'privacy-requests','staff'=>$this->auth->currentStaff(),'requests'=>$this->repo->listAll()],'staff')); }
 public function resolve(Request $request): Response
 {
  $staff=$this->auth->currentStaff();if($staff===null)return Response::redirect('/staff/login');
  if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/staff/privacy-requests');}
  $id=(int)$request->param('id',0);$status=(string)$request->input('status','IN_PROGRESS');$summary=trim((string)$request->input('resolution_summary',''));
  $existing=$this->repo->find($id);if(($existing['request_type']??'')==='ACCOUNT_DELETION'){Session::flash('error','Account deletion is Customer-controlled during the 30-day safety period and does not require Administrator approval.');return Response::redirect('/staff/privacy-requests');}
  if(!in_array($status,['IN_PROGRESS','COMPLETED','REJECTED_WITH_REASON'],true)||($status!=='IN_PROGRESS'&&mb_strlen($summary)<5)){Session::flash('error','A clear resolution summary is required when closing a request.');return Response::redirect('/staff/privacy-requests');}
  try{$before=$this->repo->find($id);$this->repo->resolve($id,(int)$staff['id'],$status,$summary);$this->audit->logStaffAction((int)$staff['id'],'PRIVACY_REQUEST_STATUS_CHANGED','privacy_request',$id,['status'=>$before['status']??null],['status'=>$status]);Session::flash('success','Privacy request #'.$id.' updated.');}catch(\Throwable){Session::flash('error','The privacy request could not be updated.');}
  return Response::redirect('/staff/privacy-requests');
 }
}
