<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\AreaRepository;
use Bulbula\Services\ReferenceDataService;
use Bulbula\Services\StaffAuthService;

final class LocationController
{
    public function __construct(private readonly StaffAuthService $staffAuth=new StaffAuthService(),private readonly AreaRepository $areaRepo=new AreaRepository(),private readonly ReferenceDataService $referenceData=new ReferenceDataService()){}
    public function index(Request $request): Response
    {
        $areas=$this->areaRepo->listAll(false);foreach($areas as &$area)$area['aliases']=$this->areaRepo->aliasesFor((int)$area['id']);unset($area);
        return Response::html(View::render('staff/locations',['pageTitle'=>'Local Areas & Launch Boundary — Staff Console','activeNav'=>'locations','staff'=>$this->staffAuth->currentStaff(),'areas'=>$areas], 'staff'));
    }
    public function save(Request $request): Response
    {
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/staff/locations');}
        $staff=$this->staffAuth->currentStaff();$result=$this->referenceData->saveArea((array)$request->input(),(int)($staff['id']??0));
        Session::flash($result['ok']?'success':'error',$result['ok']?'Controlled area saved and caches refreshed.':(string)$result['error']);return Response::redirect('/staff/locations');
    }
}
