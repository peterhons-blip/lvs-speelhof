<?php
namespace App\Services;

use RuntimeException;
use ZipArchive;

class ClassMentorSpreadsheet
{
    public function read(string $path,string $extension): array
    {
        $rows=[];
        if($extension==='csv'){
            $handle=fopen($path,'rb');
            if(!$handle) throw new RuntimeException('CSV kan niet worden geopend.');
            try{
                $line=fgets($handle);
                if($line===false) throw new RuntimeException('Leeg bestand.');
                $delimiter=substr_count($line,';')>=substr_count($line,',')?';':',';
                rewind($handle);
                while(($row=fgetcsv($handle,0,$delimiter,'"',''))!==false){
                    $rows[]=$row;
                    if(count($rows)>2001) throw new RuntimeException('Maximaal 2000 regels.');
                }
            }finally{fclose($handle);}
        }elseif($extension==='xlsx'){
            if(!class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZIP-extensie ontbreekt.');
            $zip=new ZipArchive();
            if($zip->open($path)!==true) throw new RuntimeException('Ongeldig Excel-bestand.');
            try{
                $read=function(string $name,bool $required=true)use($zip){
                    $stat=$zip->statName($name);
                    if(!$stat){if(!$required)return null;throw new RuntimeException('Excel-onderdeel ontbreekt.');}
                    if($stat['size']>8388608)throw new RuntimeException('Excel-onderdeel te groot.');
                    $raw=$zip->getFromName($name);
                    if(!is_string($raw)||stripos($raw,'<!DOCTYPE')!==false||stripos($raw,'<!ENTITY')!==false)throw new RuntimeException('Ongeldige Excel XML.');
                    $xml=@simplexml_load_string($raw,\SimpleXMLElement::class,LIBXML_NONET);
                    if(!$xml)throw new RuntimeException('Ongeldige Excel XML.');
                    return $xml;
                };
                $strings=[];
                $shared=$read('xl/sharedStrings.xml',false);
                if($shared!==null)foreach($shared->xpath('//*[local-name()="si"]') as $si){
                    $strings[]=implode('',array_map('strval',$si->xpath('.//*[local-name()="t"]')));
                }
                $workbook=$read('xl/workbook.xml');
                $sheets=$workbook->xpath('//*[local-name()="sheet"]');
                if(!$sheets)throw new RuntimeException('Geen werkblad.');
                $attrs=$sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $id=(string)($attrs['id']??'');
                $rels=$read('xl/_rels/workbook.xml.rels');
                $target='';
                foreach($rels->xpath('//*[local-name()="Relationship"]') as $rel){
                    if((string)$rel['Id']===$id && (string)$rel['TargetMode']!=='External'){$target=(string)$rel['Target'];break;}
                }
                $target=ltrim($target,'/');
                if(!str_starts_with($target,'xl/'))$target='xl/'.$target;
                if(!preg_match('~^xl/worksheets/[a-zA-Z0-9_.-]+\.xml$~D',$target))throw new RuntimeException('Ongeldig eerste werkblad.');
                $sheet=$read($target);
                foreach($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row){
                    $pair=['',''];
                    foreach($row->xpath('./*[local-name()="c"]') as $cell){
                        $ref=(string)$cell['r'];
                        if(!preg_match('/^([AB])[0-9]+$/D',$ref,$m))continue;
                        if($cell->xpath('./*[local-name()="f"]'))throw new RuntimeException('Gebruik waarden, geen formules.');
                        $type=(string)$cell['t'];
                        $v=$cell->xpath('./*[local-name()="v"]');
                        $value=(string)($v[0]??'');
                        if($type==='s')$value=$strings[(int)$value]??'';
                        if($type==='inlineStr')$value=implode('',array_map('strval',$cell->xpath('.//*[local-name()="t"]')));
                        $pair[$m[1]==='A'?0:1]=$value;
                    }
                    $rows[]=$pair;
                    if(count($rows)>2001)throw new RuntimeException('Maximaal 2000 regels.');
                }
            }finally{$zip->close();}
        }else throw new RuntimeException('Gebruik XLSX of CSV.');
        $rows=array_values(array_filter(array_map(fn($r)=>array_map(fn($v)=>trim((string)$v),array_slice(array_pad($r,2,''),0,2)),$rows),fn($r)=>$r!==['','']));
        if(!$rows)throw new RuntimeException('Geen gegevens gevonden.');
        $header=array_shift($rows);
        $header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
        if(mb_strtolower($header[0])!=='klas'||mb_strtolower($header[1])!=='usernameklastitularis')throw new RuntimeException('Kolomkoppen moeten klas en usernameKlastitularis zijn.');
        if(!$rows)throw new RuntimeException('Geen titularissen gevonden.');
        return $rows;
    }
}
