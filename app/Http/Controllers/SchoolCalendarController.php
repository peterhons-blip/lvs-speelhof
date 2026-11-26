<?php
namespace App\Http\Controllers;
use App\Services\SchoolCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SchoolCalendarController extends Controller
{
    public function index(Request $request)
    {
        $year = (int)$request->query('year', now('Europe/Brussels')->month >= 9 ? now('Europe/Brussels')->year : now('Europe/Brussels')->year-1);
        abort_unless($year >= 2000 && $year <= 2098, 422);
        $closures=DB::table('school_calendar_closures')->where('school_year',$year)->orderBy('starts_on')->get();
        return view('beheer.school-calendar',compact('year','closures'));
    }
    public function store(Request $request)
    {
        $data=$request->validate(['school_year'=>'required|integer|min:2000|max:2098','name'=>'required|string|max:120','starts_on'=>'required|date_format:Y-m-d','ends_on'=>'required|date_format:Y-m-d|after_or_equal:starts_on']);
        $year=(int)$data['school_year'];
        if ($data['starts_on'] < "$year-09-01" || $data['ends_on'] > ($year+1).'-08-31') return back()->withErrors(['starts_on'=>'De periode moet binnen het gekozen schooljaar vallen.'])->withInput();
        DB::table('school_calendar_closures')->updateOrInsert(['school_year'=>$year,'name'=>$data['name'],'source'=>'custom'],['starts_on'=>$data['starts_on'],'ends_on'=>$data['ends_on'],'updated_at'=>now()]);
        return redirect()->route('beheer.school-calendar.index',['year'=>$year])->with('success','Sluitingsperiode opgeslagen.');
    }
    public function import(Request $request)
    {
        $data=$request->validate(['school_year'=>'required|integer|min:2000|max:2098']);
        SchoolCalendar::import((int)$data['school_year']);
        return redirect()->route('beheer.school-calendar.index',['year'=>$data['school_year']])->with('success','Officiële vakanties toegevoegd.');
    }
    public function destroy(int $closure)
    {
        DB::table('school_calendar_closures')->where('id',$closure)->where('source','custom')->delete();
        return back()->with('success','Schoolgebonden sluitingsdag verwijderd.');
    }
}
