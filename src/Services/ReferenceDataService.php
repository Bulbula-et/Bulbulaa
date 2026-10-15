<?php
declare(strict_types=1);

namespace Bulbula\Services;

use Bulbula\Core\Cache;
use Bulbula\Core\Database;
use Bulbula\Repositories\AreaRepository;
use Bulbula\Repositories\TaxonomyRepository;
use Throwable;

final class ReferenceDataService
{
    public function __construct(
        private readonly TaxonomyRepository $taxonomy=new TaxonomyRepository(),
        private readonly AreaRepository $areas=new AreaRepository(),
        private readonly AuditService $audit=new AuditService()
    ) {}

    public function saveCategory(array $input,int $staffId): array
    {
        $id=(int)($input['id']??0);$before=$id>0?$this->taxonomy->findById($id):null;
        if($id>0 && $before===null)return ['ok'=>false,'error'=>'Category not found.'];
        $name=trim((string)($input['name_en']??''));$slug=strtolower(trim((string)($input['slug']??'')));
        if($name==='' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)!==1)return ['ok'=>false,'error'=>'A clear English name and lowercase hyphenated slug are required.'];
        $parentId=!empty($input['parent_id'])?(int)$input['parent_id']:null;$active=!empty($input['is_active']);
        if($parentId===$id)return ['ok'=>false,'error'=>'A category cannot be its own parent.'];
        $parent=$parentId?$this->taxonomy->findById($parentId):null;
        if($parentId && ($parent===null || (int)$parent['depth_level']!==1))return ['ok'=>false,'error'=>'Subcategories require an existing level-one parent.'];
        if($active && $parent!==null && empty($parent['is_active']))return ['ok'=>false,'error'=>'Activate the parent category before activating this subcategory.'];
        $children=$id>0?(int)Database::fetchOne('SELECT COUNT(*) AS n FROM categories WHERE parent_id=:id',[':id'=>$id])['n']:0;
        if($children>0 && $parentId!==null)return ['ok'=>false,'error'=>'A category with children cannot become a subcategory.'];
        if(!$active && $id>0){
            $assigned=(int)Database::fetchOne('SELECT (SELECT COUNT(*) FROM businesses WHERE primary_category_id=:id)+(SELECT COUNT(*) FROM business_secondary_categories WHERE category_id=:id) AS n',[':id'=>$id])['n'];
            $activeChildren=(int)Database::fetchOne('SELECT COUNT(*) AS n FROM categories WHERE parent_id=:id AND is_active=1',[':id'=>$id])['n'];
            if($assigned>0 || $activeChildren>0)return ['ok'=>false,'error'=>'Reassign Businesses and deactivate child categories before deactivating this category.'];
        }
        $aliases=$this->parseAliases((string)($input['aliases']??''));if(isset($aliases['error']))return ['ok'=>false,'error'=>$aliases['error']];
        $duplicate=Database::fetchOne('SELECT id FROM categories WHERE (LOWER(slug)=LOWER(:slug) OR LOWER(name_en)=LOWER(:name)) AND id<>:id LIMIT 1',[':slug'=>$slug,':name'=>$name,':id'=>$id]);
        if($duplicate!==null)return ['ok'=>false,'error'=>'Category name or slug duplicates an existing controlled term.'];
        if(!$this->aliasesAvailable('category_aliases','category_id',$aliases['items'],$id))return ['ok'=>false,'error'=>'An alias is already assigned to another category.'];
        $pdo=Database::pdo();try{$pdo->beginTransaction();
            $saved=$this->taxonomy->saveCategory(['id'=>$id?:null,'parent_id'=>$parentId,'slug'=>$slug,'name_en'=>$name,'name_am'=>trim((string)($input['name_am']??'')),'description_en'=>trim((string)($input['description_en']??'')),'icon_key'=>trim((string)($input['icon_key']??'grid')),'display_order'=>(int)($input['display_order']??0),'is_active'=>$active]);
            Database::execute('DELETE FROM category_aliases WHERE category_id=:id',[':id'=>$saved]);foreach($aliases['items'] as $alias)Database::insert('INSERT INTO category_aliases(category_id,alias_term,language_code) VALUES(:id,:term,:lang)',[':id'=>$saved,':term'=>$alias['term'],':lang'=>$alias['lang']]);
            $after=$this->taxonomy->findById($saved);$this->audit->logStaffAction($staffId,$id?'CATEGORY_UPDATED':'CATEGORY_CREATED','category',$saved,$before,$after);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return ['ok'=>false,'error'=>'Category change was rejected; verify uniqueness and hierarchy.'];}
        Cache::invalidateTags(['categories','branches']);return ['ok'=>true,'id'=>$saved];
    }

    public function saveArea(array $input,int $staffId): array
    {
        $id=(int)($input['id']??0);$before=$id>0?$this->areas->findById($id):null;if($id>0&&$before===null)return ['ok'=>false,'error'=>'Area not found.'];
        $name=trim((string)($input['name_en']??''));$slug=strtolower(trim((string)($input['slug']??'')));$sub=trim((string)($input['sub_city_en']??'Bole'));
        if($name===''||$sub===''||preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)!==1)return ['ok'=>false,'error'=>'Area name, sub-city, and lowercase hyphenated slug are required.'];
        $lat=$input['center_latitude']??null;$lng=$input['center_longitude']??null;if(($lat!==''&&!is_numeric($lat))||($lng!==''&&!is_numeric($lng))||($lat!==''&&((float)$lat < -90||(float)$lat>90))||($lng!==''&&((float)$lng < -180||(float)$lng>180)))return ['ok'=>false,'error'=>'Coordinates are outside valid latitude/longitude ranges.'];
        $active=!empty($input['is_active']);if(!$active&&$id>0){$assigned=(int)Database::fetchOne('SELECT COUNT(*) AS n FROM branches WHERE area_id=:id',[':id'=>$id])['n'];if($assigned>0)return ['ok'=>false,'error'=>'Reassign all Branches before deactivating this area.'];}
        $aliases=$this->parseAliases((string)($input['aliases']??''));if(isset($aliases['error']))return ['ok'=>false,'error'=>$aliases['error']];
        if(Database::fetchOne('SELECT id FROM areas WHERE (LOWER(slug)=LOWER(:slug) OR LOWER(name_en)=LOWER(:name)) AND id<>:id LIMIT 1',[':slug'=>$slug,':name'=>$name,':id'=>$id])!==null)return ['ok'=>false,'error'=>'Area name or slug duplicates an existing controlled location.'];
        if(!$this->aliasesAvailable('area_aliases','area_id',$aliases['items'],$id))return ['ok'=>false,'error'=>'An alias is already assigned to another area.'];
        $pdo=Database::pdo();try{$pdo->beginTransaction();
            $saved=$this->areas->saveArea(['id'=>$id?:null,'slug'=>$slug,'name_en'=>$name,'name_am'=>trim((string)($input['name_am']??'')),'sub_city_en'=>$sub,'sub_city_am'=>trim((string)($input['sub_city_am']??'')),'center_latitude'=>$lat,'center_longitude'=>$lng,'is_launch_area'=>!empty($input['is_launch_area']),'is_active'=>$active,'display_order'=>(int)($input['display_order']??0)]);
            Database::execute('DELETE FROM area_aliases WHERE area_id=:id',[':id'=>$saved]);foreach($aliases['items'] as $alias)Database::insert('INSERT INTO area_aliases(area_id,alias_term,language_code) VALUES(:id,:term,:lang)',[':id'=>$saved,':term'=>$alias['term'],':lang'=>$alias['lang']]);
            $after=$this->areas->findById($saved);$this->audit->logStaffAction($staffId,$id?'AREA_UPDATED':'AREA_CREATED','area',$saved,$before,$after);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return ['ok'=>false,'error'=>'Area change was rejected; verify uniqueness and assignments.'];}
        Cache::invalidateTags(['areas','branches']);return ['ok'=>true,'id'=>$saved];
    }

    private function parseAliases(string $raw): array
    {
        $items=[];$seen=[];foreach(preg_split('/[\r\n,]+/u',$raw)?:[] as $line){$line=trim($line);if($line==='')continue;$lang='en';if(preg_match('/^(en|am|translit):(.*)$/u',$line,$m)){$lang=$m[1];$line=trim($m[2]);}if($line===''||mb_strlen($line)>120)return ['error'=>'Aliases must contain 1–120 characters.'];$key=mb_strtolower($line);if(isset($seen[$key]))continue;$seen[$key]=true;$items[]=['term'=>$line,'lang'=>$lang];}
        if(count($items)>20)return ['error'=>'Use no more than 20 aliases per reference item.'];return ['items'=>$items];
    }
    private function aliasesAvailable(string $table,string $column,array $aliases,int $id): bool
    {
        foreach($aliases as $alias){$row=Database::fetchOne("SELECT {$column} AS owner FROM {$table} WHERE LOWER(alias_term)=LOWER(:term) LIMIT 1",[':term'=>$alias['term']]);if($row!==null&&(int)$row['owner']!==$id)return false;}return true;
    }
}
