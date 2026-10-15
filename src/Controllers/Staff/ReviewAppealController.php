<?php
declare(strict_types=1);
namespace Bulbula\Controllers\Staff;
use Bulbula\Core\Csrf;use Bulbula\Core\Request;use Bulbula\Core\Response;use Bulbula\Core\Session;use Bulbula\Core\View;use Bulbula\Repositories\ReviewAppealRepository;use Bulbula\Services\ReviewAppealService;use Bulbula\Services\StaffAuthService;
final class ReviewAppealController
{
 public function __construct(private readonly StaffAuthService $auth=new StaffAuthService(),private readonly ReviewAppealRepository $appeals=new ReviewAppealRepository(),private readonly ReviewAppealService $service=new ReviewAppealService()){}
 public function index(Request $request):Response{return Response::html(View::render('staff/review-appeals',['pageTitle'=>'Review Appeals — Staff Console','activeNav'=>'moderation','staff'=>$this->auth->currentStaff(),'appeals'=>$this->appeals->listAll()], 'staff'));}
 public function decide(Request $request):Response{if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/staff/review-appeals');}$staff=$this->auth->currentStaff();$result=$this->service->decide((int)$request->param('appeal_id',0),(int)($staff['id']??0),(string)$request->input('outcome',''),(string)$request->input('reason_code',''),(string)$request->input('decision_notes',''));Session::flash($result['ok']?'success':'error',$result['ok']?'Final appeal decision recorded and Customer notified.':(string)$result['error']);return Response::redirect('/staff/review-appeals');}
}
