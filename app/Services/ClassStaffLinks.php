<?php
namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClassStaffLinks
{
    public static function year(): string
    {
        $now=now('Europe/Brussels');
        $start=$now->month>=9?$now->year:$now->year-1;
        return $start.'-'.($start+1);
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u',' ',$name)));
    }

    public function classes()
    {
        return DB::table('klassen')->where('schoolid',1)
            ->whereIn('id',DB::table('leerlingen')->where('schoolid',1)->where('active',1)->select('klasid'))
            ->orderBy('klasnaam')->get();
    }

    private function staff()
    {
        return DB::table('users')->where('schoolid',1)->where(fn($q)=>$q->whereNull('smartschool_active')->orWhere('smartschool_active',1))
            ->whereNotNull('smartschool_gebruikersnaam')->get()
            ->reject(fn($u)=>in_array($this->normalize($u->smartschool_gebruikersnaam),['ict','lvs','bib','beleid',''],true))
            ->groupBy(fn($u)=>$this->normalize($u->smartschool_gebruikersnaam));
    }

    public function teacherPreview(): array
    {
        $school=School::findOrFail(1);
        if (!trim((string)$school->smartschool_wsdl)||!trim((string)$school->smartschool_accesscode)) throw new RuntimeException('Smartschool-configuratie ontbreekt.');
        $soap=new SmartschoolSoap($school->smartschool_wsdl,$school->smartschool_accesscode);
        // Group names are also accepted by getAllAccountsExtended (as in staff sync).
        // Prefer a uniquely discovered code; tolerate differences in whitespace.
        try { $groups=$soap->getAllGroupsAndClasses(); } catch (\Throwable $e) { $groups=[]; }
        $byName=[];
        foreach($groups as $code=>$groupName) {
            $key=preg_replace('/\s+/u','',$this->normalize((string)$groupName));
            $byName[$key][]=(string)$code;
        }
        $staff=$this->staff(); $rows=[]; $errors=[]; $covered=[]; $notes=[]; $accountCache=[];
        foreach($this->classes() as $class){
            $name='Lkr '.$class->klasnaam;
            $key=preg_replace('/\s+/u','',$this->normalize($name));
            $codes=array_values(array_unique($byName[$key]??[]));
            if(count($codes)>1){$errors[]=$name.': meerdere groepen gevonden (codes: '.implode(', ',$codes).').';continue;}
            if(!$codes){
                // Only numeric split suffixes; do not conflate distinct tracks such as EWt/EWw.
                if(preg_match('/^(lkr[0-9]+[a-z]+)[0-9]+$/D',$key,$match)){
                    $codes=array_values(array_unique($byName[$match[1]]??[]));
                    if(count($codes)>1){$errors[]=$name.': gezamenlijke groep is dubbel.';continue;}
                }elseif(preg_match('/^lkr[0-9]+[a-z]+$/D',$key)){
                    $split=[];
                    foreach($byName as $groupKey=>$groupCodes){
                        if(!preg_match('/^'.preg_quote($key,'/').'[0-9]+$/D',$groupKey))continue;
                        $groupCodes=array_values(array_unique($groupCodes));
                        if(count($groupCodes)!==1){$errors[]=$name.': gesplitste groep is dubbel.';continue 2;}
                        $split[$groupKey]=$groupCodes[0];
                    }
                    if(count($split)>=2){ksort($split);$codes=array_values(array_unique($split));}
                }
                if($codes)$notes[]=$name.' → '.implode(' + ',$codes).' (afwijkende klasindeling; controleer koppeling).';
            }
            $identifiers=$codes?:[$name];
            $identifier=implode(' + ',$identifiers);
            try{
                $accounts=[];
                foreach($identifiers as $group){
                    if(!isset($accountCache[$group])){
                        // Recursive fetch includes members in nested subgroups as well.
                        $result=$soap->getAllAccountsExtended($group,true);
                        if(!array_is_list($result)){
                            $result=$result['accounts']??$result['users']??$result['data']??$result['items']??$result['result']??null;
                        }
                        if(!is_array($result)||!array_is_list($result))throw new RuntimeException('ongeldige structuur van ledenlijst');
                        if(!$result)throw new RuntimeException('lege ledenlijst, ook bij recursieve opvraging');
                        $accountCache[$group]=$result;
                    }
                    array_push($accounts,...$accountCache[$group]);
                }
                $local=[]; $valid=true;
                foreach($accounts as $account){
                    if(!is_array($account)){$valid=false;break;}
                    $username=$this->normalize((string)($account['gebruikersnaam']??$account['username']??''));
                    if(in_array($username,['ict','lvs','bib','beleid'],true)) continue;
                    if(in_array((string)($account['basisrol']??''),['1','leerling','student'],true)||$username===''||!isset($staff[$username])||$staff[$username]->count()!==1){
                        $errors[]=$name.': onbekend of ongeldig personeelsaccount '.$username;
                        $valid=false;continue;
                    }
                    $u=$staff[$username]->first();
                    $local[$u->id]=['class_id'=>(int)$class->id,'user_id'=>(int)$u->id,'class'=>$class->klasnaam,'username'=>$username];
                }
                if(!$valid||!$local){$errors[]=$name.': bestaande koppelingen behouden.';continue;}
                $covered[]=(int)$class->id;
                array_push($rows,...array_values($local));
            }catch(\Throwable $e){
                $reason=$e instanceof RuntimeException?$e->getMessage():($e instanceof \SoapFault?'SOAP-fout ('.$e->faultcode.')':'technische fout ('.class_basename($e).')');
                $errors[]=$name.': '.($codes?'groep gevonden: '.$identifier:'niet in groepslijst; rechtstreeks op naam geprobeerd').'; '.$reason.'. Bestaande koppelingen behouden.';
            }
        }
        return compact('rows','errors','covered','notes');
    }

    public function smartschoolMentorPreview(): array
    {
        $school=School::findOrFail(1);
        if (!trim((string)$school->smartschool_wsdl)||!trim((string)$school->smartschool_accesscode)) throw new RuntimeException('Smartschool-configuratie ontbreekt.');
        $soap=new SmartschoolSoap($school->smartschool_wsdl,$school->smartschool_accesscode);
        $accounts=$soap->getClassTeachers();
        if (!array_is_list($accounts)||!$accounts) throw new RuntimeException('Geen geldige titularissenlijst ontvangen.');
        $classes=$this->classes();
        $byCode=$classes->groupBy(fn($c)=>(string)$c->smartschool_code);
        $byName=$classes->groupBy(fn($c)=>$this->normalize($c->klasnaam));
        $staff=$this->staff();$rows=[];$errors=[];$notes=[];$covered=[];
        foreach($accounts as $account){
            if(!is_array($account)){$errors[]='Ongeldig titularisrecord.';continue;}
            if(!in_array($account['isOfficial']??null,[true,1,'1','true'],true))continue;
            if((string)($account['instellingsnummer']??'')!==(string)$school->instellingsnummer)continue;
            $code=trim((string)($account['klasid']??''));
            $name=trim((string)($account['klasnaam']??''));
            $username=$this->normalize((string)($account['gebruikersnaam']??''));
            if($code===''||$name===''||$username===''){$errors[]='Onvolledig titularisrecord.';continue;}
            $matched=$byCode->get($code,collect());
            if($matched->isEmpty()){
                // Ignore inactive classes; a name match with a different code is a conflict.
                if($byName->has($this->normalize($name)))$errors[]=$name.': klas-ID wijkt af; controleer leerlingensync.';
                continue;
            }
            if($matched->count()!==1 || $this->normalize($matched->first()->klasnaam)!==$this->normalize($name)){
                $errors[]=$name.': klas-ID niet eenduidig gekoppeld.';continue;
            }
            $class=$matched->first();
            $user=$staff->get($username,collect());
            if($user->count()!==1){$errors[]=$name.': onbekend of dubbel personeelsaccount '.$username;continue;}
            $u=$user->first();
            $rows[$class->id.':'.$u->id]=['class_id'=>(int)$class->id,'user_id'=>(int)$u->id,'class'=>$class->klasnaam,'username'=>$username];
            $covered[]=(int)$class->id;
        }
        $covered=array_values(array_unique($covered));
        foreach($classes as $class)if(!in_array((int)$class->id,$covered,true))$notes[]=$class->klasnaam.': geen titularis ontvangen; bestaande koppelingen behouden.';
        return ['rows'=>array_values($rows),'errors'=>$errors,'covered'=>$covered,'notes'=>$notes];
    }

    public function mentorPreview(array $input): array
    {
        $classes=$this->classes()->groupBy(fn($c)=>$this->normalize($c->klasnaam));
        $staff=$this->staff();$rows=[];$errors=[];$covered=[];
        foreach($input as $i=>$pair){
            [$class,$username]=array_pad($pair,2,'');
            $c=$classes[$this->normalize((string)$class)]??collect();
            $u=$staff[$this->normalize((string)$username)]??collect();
            if($c->count()!==1||$u->count()!==1){$errors[]='Rij '.($i+2).': onbekende of dubbele klas/gebruiker: '.$class.' / '.$username;continue;}
            $cl=$c->first();$user=$u->first();
            $rows[$cl->id.':'.$user->id]=['class_id'=>(int)$cl->id,'user_id'=>(int)$user->id,'class'=>$cl->klasnaam,'username'=>$user->smartschool_gebruikersnaam];
            $covered[]=(int)$cl->id;
        }
        return ['rows'=>array_values($rows),'errors'=>$errors,'covered'=>array_values(array_unique($covered))];
    }

    public function apply(array $preview,string $kind): int
    {
        if(!in_array($kind,['teacher','mentor','mentor_smartschool'],true)||$preview['errors']||!$preview['rows']) throw new RuntimeException('Controleer eerst alle fouten; niets gewijzigd.');
        $source=$kind==='mentor'?'excel':'smartschool';
        $kind=$kind==='mentor_smartschool'?'mentor':$kind;
        $year=self::year();
        return DB::transaction(function()use($preview,$kind,$source,$year){
            $classes=$this->classes()->pluck('id')->map(fn($id)=>(int)$id)->all();
            $staff=$this->staff()->filter(fn($g)=>$g->count()===1)->map(fn($g)=>(int)$g->first()->id)->values()->all();
            foreach($preview['rows'] as $row){
                if(!in_array($row['class_id'],$classes,true)||!in_array($row['user_id'],$staff,true)) throw new RuntimeException('Klas of personeelsaccount gewijzigd. Maak een nieuwe preview.');
            }
            // Only replace the same source/kind for explicitly covered classes.
            DB::table('class_staff_links')->where('school_id',1)->where('school_year',$year)->where('kind',$kind)->whereIn('class_id',$preview['covered'])->delete();
            foreach($preview['rows'] as $row){
                DB::table('class_staff_links')->insertOrIgnore(['school_id'=>1,'school_year'=>$year,'class_id'=>$row['class_id'],'user_id'=>$row['user_id'],'kind'=>$kind,'source'=>$source,'created_at'=>now(),'updated_at'=>now()]);
            }
            return count($preview['rows']);
        });
    }
}
