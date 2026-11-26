<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\LibraryAccess;
class LibraryInventoryController extends Controller {
    private function actor(Request $r): string {return app(LibraryAccess::class)->username($r);}
    public function index() {
        $runs=DB::table('library_inventory_runs')->orderByDesc('id')->paginate(20);
        return view('bibliotheek.inventory.index',compact('runs'));
    }
    public function store(Request $r) {
        $data=$r->validate(['name'=>'required|string|max:120']);
        $id=DB::transaction(function() use($r,$data){
            $id=DB::table('library_inventory_runs')->insertGetId(['name'=>$data['name'],'actor'=>$this->actor($r),'created_at'=>now(),'updated_at'=>now()]);
            DB::table('library_copies')->whereIn('status',['beschikbaar','uitgeleend','beschadigd'])->orderBy('id')->chunkById(500,function($copies) use($id){
                DB::table('library_inventory_items')->insert($copies->map(fn($c)=>['run_id'=>$id,'copy_id'=>$c->id,'initial_status'=>$c->status])->all());
            });
            return $id;
        });
        return redirect()->route('bibliotheek.inventory.show',$id);
    }
    public function show(Request $r,int $run) {
        $item=DB::table('library_inventory_runs')->find($run); abort_unless($item,404);
        $base=DB::table('library_inventory_items as i')->join('library_copies as c','c.id','=','i.copy_id')->join('library_books as b','b.id','=','c.book_id')->where('i.run_id',$run);
        $stats=['total'=>(clone $base)->count(),'seen'=>(clone $base)->whereNotNull('i.seen_at')->count(),'loaned'=>(clone $base)->whereNull('i.seen_at')->where('i.initial_status','uitgeleend')->count(),'missing'=>(clone $base)->whereNull('i.seen_at')->where('i.initial_status','<>','uitgeleend')->count()];
        $filter=$r->query('filter','missing');
        if($filter==='missing') $base->whereNull('i.seen_at')->where('i.initial_status','<>','uitgeleend');
        if($filter==='seen') $base->whereNotNull('i.seen_at');
        if($filter==='loaned') $base->whereNull('i.seen_at')->where('i.initial_status','uitgeleend');
        $copies=$base->select('i.*','c.barcode','c.status','c.shelf_location','b.title')->orderBy('b.title')->orderBy('c.barcode')->paginate(50)->withQueryString();
        return view('bibliotheek.inventory.show',compact('item','stats','copies','filter'));
    }
    public function scan(Request $r,int $run) {
        $data=$r->validate(['barcode'=>'required|string|max:60']);
        $result=DB::transaction(function() use($r,$run,$data){
            $item=DB::table('library_inventory_runs')->where('id',$run)->lockForUpdate()->first(); abort_unless($item,404); abort_if($item->finished_at,409);
            $copy=DB::table('library_copies')->where('barcode',trim($data['barcode']))->first();
            if(!$copy) return 'Onbekende barcode.';
            $row=DB::table('library_inventory_items')->where('run_id',$run)->where('copy_id',$copy->id);
            $entry=$row->first(); if(!$entry) return 'Exemplaar behoort niet tot deze inventaris.';
            if($entry->seen_at) return 'Exemplaar was al gecontroleerd.';
            $row->update(['seen_at'=>now(),'seen_by'=>$this->actor($r)]); return 'Gecontroleerd: '.$copy->barcode;
        });
        return back()->with('success',$result);
    }
    public function finish(int $run) {
        DB::table('library_inventory_runs')->where('id',$run)->whereNull('finished_at')->update(['finished_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Inventaris afgesloten. Er zijn geen voorraadstatussen gewijzigd.');
    }
}
