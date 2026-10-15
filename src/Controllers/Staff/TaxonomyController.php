<?php
declare(strict_types=1);

namespace Bulbula\Controllers\Staff;

use Bulbula\Core\Csrf;
use Bulbula\Core\Request;
use Bulbula\Core\Response;
use Bulbula\Core\Session;
use Bulbula\Core\View;
use Bulbula\Repositories\TaxonomyRepository;
use Bulbula\Services\ReferenceDataService;
use Bulbula\Services\StaffAuthService;

final class TaxonomyController
{
    public function __construct(private readonly StaffAuthService $staffAuth=new StaffAuthService(),private readonly TaxonomyRepository $taxonomyRepo=new TaxonomyRepository(),private readonly ReferenceDataService $referenceData=new ReferenceDataService()){}
    public function index(Request $request): Response
    {
        $categories=$this->taxonomyRepo->getFullTree(false);
        foreach($categories as &$category){$category['aliases']=$this->taxonomyRepo->aliasesFor((int)$category['id']);foreach($category['subcategories'] as &$sub)$sub['aliases']=$this->taxonomyRepo->aliasesFor((int)$sub['id']);unset($sub);}unset($category);
        return Response::html(View::render('staff/taxonomy',['pageTitle'=>'Taxonomy Catalogue — Staff Console','activeNav'=>'taxonomy','staff'=>$this->staffAuth->currentStaff(),'categories'=>$categories], 'staff'));
    }
    public function save(Request $request): Response
    {
        if(!Csrf::verify((string)$request->input('_csrf',''))){Session::flash('error','Security token expired.');return Response::redirect('/staff/taxonomy');}
        $staff=$this->staffAuth->currentStaff();$result=$this->referenceData->saveCategory((array)$request->input(),(int)($staff['id']??0));
        Session::flash($result['ok']?'success':'error',$result['ok']?'Controlled category saved and caches refreshed.':(string)$result['error']);return Response::redirect('/staff/taxonomy');
    }
}
