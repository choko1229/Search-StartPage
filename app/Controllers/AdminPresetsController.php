<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth\Auth;
use App\Helpers\View;
use App\Http\{Request,Response,HttpException};
use App\Repositories\ProviderPresetRepository;
use App\Services\{ProviderPresets,PresetState,AdminAuditLogger};

final class AdminPresetsController
{
    public function __construct(private readonly ProviderPresetRepository $repository,private readonly PresetState $state,private readonly Auth $auth,private readonly AdminAuditLogger $audit,private readonly View $view){}
    public function read(Request $request):Response
    {
        $data=$this->repository->read();return $request->isApi()?Response::json($data):new Response($this->view->render('admin-presets',$data));
    }
    public function update(Request $request):Response
    {
        $version=$request->body['version']??null;$presets=$request->jsonObject?->presets??null;
        if(!$request->isApi()){
            if(!is_string($version)||!preg_match('/^[1-9][0-9]{0,15}$/D',$version))throw new HttpException(422,'INVALID_INPUT');$version=(int)$version;$presets=[];
            foreach(['web','ai'] as $mode){
                $input=$request->body[$mode]??null;if(!is_array($input)||count($input)>51)throw new HttpException(422,'INVALID_INPUT');$presets[$mode]=[];
                foreach($input as $row){
                    if(!is_array($row))throw new HttpException(422,'INVALID_INPUT');
                    if(($row['remove']??null)==='1')continue;
                    if(($row['id']??null)===''&&($row['name']??null)===''&&($row['url']??null)===''&&($row['prefix']??null)===''&&($row['icon']??null)==='')continue;
                    foreach(['id','name','url','prefix','icon','enabled','copy','sort_order'] as $field)if(!isset($row[$field])||!is_string($row[$field]))throw new HttpException(422,'INVALID_INPUT');
                    if(!in_array($row['enabled'],['0','1'],true)||!in_array($row['copy'],['0','1'],true)||!preg_match('/^(0|[1-9][0-9]{0,3})$/D',$row['sort_order']))throw new HttpException(422,'INVALID_INPUT');
                    unset($row['remove']);$row['enabled']=$row['enabled']==='1';$row['copy']=$row['copy']==='1';$row['sort_order']=(int)$row['sort_order'];$presets[$mode][]=$row;
                }
            }
        }
        if(!is_int($version)||$version<1||$version>=9007199254740990)throw new HttpException(422,'INVALID_INPUT');
        $result=$this->repository->update(ProviderPresets::validate($presets),$version,(int)$this->auth->requireUser()['id'],$this->state);$this->audit->flush();
        return $request->isApi()?Response::json($result):Response::redirect('/admin/presets');
    }
}
