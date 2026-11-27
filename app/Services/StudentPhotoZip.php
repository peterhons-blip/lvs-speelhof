<?php

namespace App\Services;

use ZipArchive;
use RuntimeException;

class StudentPhotoZip
{
    public const MAX_ENTRIES=2500;
    public const MAX_IMAGE=5242880;
    public const MAX_TOTAL=209715200;

    public function inspect(string $path, array $students, ?string $directory=null, bool $replace=false): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP-extensie ontbreekt.');
        $zip=new ZipArchive();
        if ($zip->open($path)!==true) throw new RuntimeException('Het ZIP-bestand kan niet worden geopend.');
        $report=['matched'=>0,'unknown'=>0,'invalid'=>0,'duplicates'=>0,'existing'=>0,'saved'=>0,'errors'=>0,'examples'=>[]];
        try {
            if ($zip->numFiles>self::MAX_ENTRIES) throw new RuntimeException('Maximaal 2500 bestanden per ZIP.');
            $total=0; $entries=[]; $counts=[];
            for ($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i);
                if (!$stat) throw new RuntimeException('Onleesbare ZIP-inhoud.');
                $total+=(int)$stat['size'];
                if ($total>self::MAX_TOTAL) throw new RuntimeException('Maximaal 200 MB uitgepakte inhoud.');
                $name=str_replace('\\','/',$stat['name']);
                if (str_ends_with($name,'/')) continue;
                if (!preg_match('/\A([0-9]{1,40})\.(jpg|jpeg|png|webp)\z/iD',basename($name),$m) || preg_match('~(?:^|/)\.\.(?:/|$)|^/|^[A-Za-z]:~',$name) || $stat['size']>self::MAX_IMAGE || $stat['size']<1) {
                    $report['invalid']++; continue;
                }
                $key=$m[1]; $counts[$key]=($counts[$key]??0)+1;
                $entries[]=[$i,$key];
            }
            foreach ($entries as [$i,$key]) {
                if ($counts[$key]>1 || (isset($students[$key]) && $students[$key]!==1)) { $report['duplicates']++; continue; }
                if (!isset($students[$key])) { $report['unknown']++; if(count($report['examples'])<20) $report['examples'][]=$key; continue; }
                $bytes=$zip->getFromIndex($i,self::MAX_IMAGE+1);
                $info=is_string($bytes)?@getimagesizefromstring($bytes):false;
                $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$info['mime']??'']??null;
                if (!$ext || strlen($bytes)>self::MAX_IMAGE || $info[0]>4000 || $info[1]>4000 || $info[0]<1 || $info[1]<1) { $report['invalid']++; continue; }
                $report['matched']++;
                if ($directory===null) continue;
                $old=glob($directory.DIRECTORY_SEPARATOR.$key.'.*')?:[];
                $old=array_filter($old,fn($p)=>in_array(strtolower(pathinfo($p,PATHINFO_EXTENSION)),['jpg','jpeg','png','webp'],true));
                if ($old && !$replace) { $report['existing']++; continue; }
                $target=$directory.DIRECTORY_SEPARATOR.$key.'.'.$ext;
                if (is_link($target)) { $report['errors']++; continue; }
                $temp=tempnam($directory,'portrait-');
                if ($temp===false) { $report['errors']++; continue; }
                try {
                    if (file_put_contents($temp,$bytes)!==strlen($bytes) || !rename($temp,$target)) { $report['errors']++; continue; }
                    foreach ($old as $previous) {
                        if (strcasecmp($previous,$target)!==0 && !@unlink($previous)) $report['errors']++;
                    }
                    $report['saved']++;
                } finally { if (is_file($temp)) @unlink($temp); }
            }
        } finally { $zip->close(); }
        return $report;
    }
}
