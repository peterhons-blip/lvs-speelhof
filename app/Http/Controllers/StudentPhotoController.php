<?php

namespace App\Http\Controllers;

use App\Services\LibraryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class StudentPhotoController extends Controller
{
    private function student(int $id): object
    {
        $student=DB::table('leerlingen')->where('schoolid',1)->where('id',$id)->first();
        abort_unless($student,404);
        return $student;
    }

    private function key(object $student): ?string
    {
        $number=(string)($student->InformatInternNr??'');
        if (!preg_match('/\A[0-9]{1,40}\z/D',$number)) return null;
        if (DB::table('leerlingen')->where('schoolid',1)->where('InformatInternNr',$number)->where('id','<>',$student->id)->exists()) return null;
        return $number;
    }

    private function directory(): string
    {
        return storage_path('app/private/student-photos/1');
    }

    public function show(Request $request, LibraryAccess $access, int $student)
    {
        abort_unless($access->staffId($request) && ($access->has($request,'beheer.students.manage') || $access->has($request,'lvs.view') || $access->has($request,'lvs.meldingen.create')),403);
        $row=$this->student($student);
        abort_unless($row->active || $access->has($request,'beheer.students.manage'),404);
        $key=$this->key($row);
        if ($key!==null) {
            foreach (['jpg','jpeg','png','webp','JPG','JPEG','PNG','WEBP'] as $ext) {
                $path=$this->directory().DIRECTORY_SEPARATOR.$key.'.'.$ext;
                if (!is_file($path) || is_link($path)) continue;
                $info=@getimagesize($path);
                if (!$info || !in_array($info['mime'],['image/jpeg','image/png','image/webp'],true)) continue;
                return response()->file($path,['Content-Type'=>$info['mime'],'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Disposition'=>'inline']);
            }
        }
        return response('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" rx="12" fill="#e5edf2"/><circle cx="50" cy="35" r="17" fill="#7893a6"/><path d="M18 88a32 32 0 0 1 64 0" fill="#7893a6"/></svg>',200,['Content-Type'=>'image/svg+xml','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }

    public function upload(Request $request, LibraryAccess $access, int $student)
    {
        $access->requirePermission($request,'beheer.students.edit');
        abort_unless($access->staffId($request),403);
        $row=$this->student($student);
        $key=$this->key($row);
        if ($key===null) throw ValidationException::withMessages(['photo'=>'Geen geldig en uniek Informat-internnummer beschikbaar.']);
        $request->validate(['photo'=>'required|file|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=4000,max_height=4000']);
        $file=$request->file('photo');
        $info=@getimagesize($file->getRealPath());
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$info['mime']??'']??null;
        abort_unless($ext,422);
        $directory=$this->directory();
        File::ensureDirectoryExists($directory,0700,true);
        $file->move($directory,$key.'.'.$ext);
        foreach (['jpg','jpeg','png','webp','JPG','JPEG','PNG','WEBP'] as $old) {
            if (strtolower($old)!==$ext) File::delete($directory.DIRECTORY_SEPARATOR.$key.'.'.$old);
        }
        return back()->with('success','Leerlingfoto opgeslagen.');
    }
}
