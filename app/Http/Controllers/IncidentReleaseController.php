<?php
namespace App\Http\Controllers;
use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentReleaseController extends Controller
{
    public function index(Request $request,LibraryAccess $access)
    {
        abort_unless($access->role($request)==='ict_coordinator',403);
        $types=DB::table('meldingssoorten as s')->leftJoin('meldingscategorien as c','c.id','=','s.meldingscategorieId')->select('s.id','s.naam','s.visible_student','s.visible_coaccount','c.naam as categorie')->orderBy('c.naam')->orderBy('s.naam')->get();
        return view('beheer.incident-release',compact('types'));
    }
    public function save(Request $request,LibraryAccess $access)
    {
        abort_unless($access->role($request)==='ict_coordinator',403);
        $data=$request->validate(['rights'=>'required|array','rights.*.student'=>'required|in:0,1','rights.*.coaccount'=>'required|in:0,1']);
        DB::transaction(function()use($data){
            foreach($data['rights'] as $id=>$values){
                abort_unless(ctype_digit((string)$id),422);
                DB::table('meldingssoorten')->where('id',(int)$id)->update(['visible_student'=>(int)$values['student'],'visible_coaccount'=>(int)$values['coaccount']]);
            }
        });
        return back()->with('success','Standaardvrijgave opgeslagen. Bestaande meldingen blijven ongewijzigd.');
    }
}
