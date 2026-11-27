<?php
namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use App\Services\LvsIncidentVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LvsVisibilityController extends Controller
{
    private function authorizeMatrix(Request $request,LibraryAccess $access): void
    {
        $access->requirePermission($request,'lvs.visibility.manage');
        abort_unless($access->role($request)==='ict_coordinator' && $access->staffId($request),403);
    }
    public function index(Request $request,LibraryAccess $access)
    {
        $this->authorizeMatrix($request,$access);
        return view('lvs.visibility',['matrix'=>LvsIncidentVisibility::matrix(),'labels'=>LvsIncidentVisibility::LABELS,'ready'=>Schema::hasTable('lvs_visibility_matrix')]);
    }
    public function save(Request $request,LibraryAccess $access)
    {
        $this->authorizeMatrix($request,$access);
        abort_unless(Schema::hasTable('lvs_visibility_matrix'),503);
        $data=$request->validate(['matrix'=>'required|array','matrix.*'=>'required|array','matrix.*.*'=>'required|boolean']);
        abort_if(array_diff(array_keys($data['matrix']),array_keys(LvsIncidentVisibility::LABELS)),422);
        $next=[];
        foreach(LvsIncidentVisibility::LABELS as $key=>$label){
            $row=$data['matrix'][$key]??[];
            abort_if(array_diff(array_keys($row),LvsIncidentVisibility::FIELDS)||array_diff(LvsIncidentVisibility::FIELDS,array_keys($row)),422);
            foreach(LvsIncidentVisibility::FIELDS as $field)$next[$key][$field]=(bool)$row[$field];
        }
        DB::transaction(function()use($next,$request,$access){
            $before=LvsIncidentVisibility::matrix();
            foreach($next as $key=>$row)DB::table('lvs_visibility_matrix')->updateOrInsert(['school_id'=>1,'relation'=>$key],$row+['updated_at'=>now(),'created_at'=>now()]);
            DB::table('portal_permission_audit')->insert(['actor'=>$access->username($request),'target'=>'lvs:visibility:1','changes'=>json_encode(['before'=>$before,'after'=>$next],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        });
        return back()->with('success','Zichtbaarheid opgeslagen. De nieuwe regels gelden ook voor bestaande meldingen.');
    }
}
