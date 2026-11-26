<?php
namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentReleaseController extends Controller
{
    public function index(Request $request,LibraryAccess $access)
    {
        $access->requirePermission($request,'lvs.taxonomy.manage');
        $categories=DB::table('meldingscategorien')->orderBy('portal_sort')->orderBy('naam')->get();
        $types=DB::table('meldingssoorten')->orderBy('portal_sort')->orderBy('naam')->get();
        return view('beheer.incident-release',compact('categories','types'));
    }

    public function save(Request $request,LibraryAccess $access)
    {
        $access->requirePermission($request,'lvs.taxonomy.manage');
        $data=$request->validate([
            'kind'=>'required|in:category,type','id'=>'nullable|integer|min:1',
            'naam'=>'required|string|max:120','portal_sort'=>'required|integer|min:0|max:99999',
            'portal_active'=>'required|in:0,1','category_id'=>'required_if:kind,type|nullable|integer|exists:meldingscategorien,id',
            'visible_student'=>'nullable|in:0,1','visible_coaccount'=>'nullable|in:0,1',
        ]);
        $name=trim($data['naam']);
        if ($name==='') throw ValidationException::withMessages(['naam'=>'Vul een naam in.']);
        $id=(int)($data['id']??0);
        $type=$data['kind']==='type';
        $table=$type?'meldingssoorten':'meldingscategorien';
        DB::transaction(function()use($data,$name,$id,$type,$table,$request,$access){
            $before=$id?DB::table($table)->where('id',$id)->lockForUpdate()->first():null;
            abort_if($id&&!$before,404);
            $duplicate=DB::table($table)->where('naam',$name)->when($id,fn($q)=>$q->where('id','<>',$id));
            if($type) $duplicate->where('meldingscategorieId',$data['category_id']);
            if($duplicate->exists()) throw ValidationException::withMessages(['naam'=>'Deze naam bestaat al.']);
            $values=['naam'=>$name,'portal_sort'=>(int)$data['portal_sort'],'portal_active'=>(int)$data['portal_active']];
            if($type){
                if($before && (int)$before->meldingscategorieId!==(int)$data['category_id'] &&
                    (DB::table('meldingen')->where('meldingssoortId',$id)->exists() ||
                    DB::table('lvs_followup_rules')->where('type_id',$id)->exists()))
                    throw ValidationException::withMessages(['category_id'=>'Deze soort is al in gebruik. Maak een nieuwe soort in de andere categorie en deactiveer de oude.']);
                $values+=['meldingscategorieId'=>(int)$data['category_id'],'visible_student'=>$request->boolean('visible_student'),'visible_coaccount'=>$request->boolean('visible_coaccount')];
            }
            if($id) DB::table($table)->where('id',$id)->update($values);
            else {
                if($type) $values+=['omschrijving'=>''];
                $id=DB::table($table)->insertGetId($values);
            }
            DB::table('portal_permission_audit')->insert(['actor'=>$access->username($request),'target'=>$table.':'.$id,'changes'=>json_encode(['before'=>$before,'after'=>$values]),'created_at'=>now(),'updated_at'=>now()]);
        });
        return redirect()->route('beheer.incident-release.index')->with('success','Instellingen opgeslagen.');
    }
}
