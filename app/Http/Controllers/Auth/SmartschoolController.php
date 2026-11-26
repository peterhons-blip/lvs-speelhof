<?php


namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SmartschoolController extends Controller
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $oauthUrl;

    public function __construct()
    {
        $this->clientId = env('SMARTSCHOOL_CLIENT_ID');
        $this->clientSecret = env('SMARTSCHOOL_CLIENT_SECRET');
        $this->redirectUri = env('SMARTSCHOOL_REDIRECT_URI');
        $this->oauthUrl = env('SMARTSCHOOL_URL');
    }

    public function redirect(Request $request)
    {
        $state = Str::random(64);
        $request->session()->put('smartschool_oauth_state',$state);
        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'userinfo',
            'state' => $state,
        ]);

        return redirect("{$this->oauthUrl}/OAuth?{$query}");
    }


    // 🔹 Callback van Smartschool
    public function callback(Request $request)
    {
        $state=$request->session()->pull('smartschool_oauth_state');
        if (!is_string($state) || !is_string($request->query('state')) || !hash_equals($state,$request->query('state'))) {
            return redirect('/')->with('error','Ongeldige aanmeldsessie. Probeer opnieuw.');
        }
        $code = $request->get('code');

        if (!$code) {
            return redirect('/')->with('error', 'Geen autorisatiecode ontvangen.');
        }

        // Access token ophalen
        $response = Http::asForm()->post('https://oauth.smartschool.be/OAuth/index/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        $data = $response->json();

        if (!isset($data['access_token'])) {
            return redirect('/')->with('error', 'Geen toegangstoken ontvangen.');
        }


        $accessToken = $data['access_token'];

        // User info ophalen
        $userResponse = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}"
        ])->get("https://oauth.smartschool.be/Api/V1/userinfo");

        if (!$userResponse->ok()) {
            throw new \Exception('Kon gebruikersinformatie niet ophalen.');
        }

        $user = $userResponse->object(); // ->userID, ->name, ->surname, ->username, ->platform

        $expectedHost=parse_url(str_contains((string)$this->oauthUrl,'://')?(string)$this->oauthUrl:'https://'.$this->oauthUrl,PHP_URL_HOST);
        $platform=(string)($user->platform??'');
        $actualHost=parse_url(str_contains($platform,'://')?$platform:'https://'.$platform,PHP_URL_HOST);
        $main=in_array($user->isMainAccount??null,[1,'1',true],true);
        $co=in_array($user->isCoAccount??null,[1,'1',true],true);
        $username=trim((string)($user->username??''));
        if (!$expectedHost || !$actualHost || strcasecmp($expectedHost,$actualHost)!==0 || $main===$co || $username==='' || empty($user->userID)) {
            return redirect('/')->with('error','Smartschool-accounttype of schoolplatform kon niet worden bevestigd.');
        }
        $baseUser=$main ? User::where('smartschool_gebruikersnaam',$username)->where('schoolid',1)->first() : null;
        if ($co || !$baseUser) {
            $role=$co?'coaccount':'leerling';
            $access=app(\App\Services\ExternalPortalAccess::class);
            $student=$access->student($username,$role);
            if (!$student || !$access->has($role,'dashboard.view',(int)$student->id)) {
                return redirect('/')->with('error','Je account heeft geen toegang tot dit portaal. Contacteer de school.');
            }
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('ss_user',[
                'id'=>(string)$user->userID,'voornaam'=>(string)($user->actualUserName??$user->name??''),
                'naam'=>(string)($user->actualUserSurname??$user->surname??''),
                'gebruikersnaam'=>$username,'platform'=>$platform,'principal_type'=>$role,
                'leerling_id'=>(int)$student->id,'groupname'=>$co?'Co-account':'Leerling',
            ]);
            return redirect()->route('external.dashboard');
        }

        // 1) Bestaat de gebruiker lokaal?


        if (!$baseUser) {
            return redirect('/')->with('error', 'Je account is niet gekend in het LVS. Contacteer de beheerder.');
        }

        // 2) Actuele Smartschool-status is onafhankelijk van de handmatige loginblokkering.
        if ($baseUser->smartschool_active !== null && (int) $baseUser->smartschool_active === 0) {
            return redirect('/')->with('error', 'Je account is niet meer actief volgens Smartschool.');
        }

        // 2) Is login toegestaan / account actief?
        if ((int)($baseUser->allowed_login ?? 0) !== 1) {   // of: $baseUser->active
            return redirect('/')->with('error', 'Je account is gedeactiveerd. Contacteer de beheerder.');
        }

        // 3) Nu pas: groepnaam ophalen
        $dbUser = User::query()
            ->leftJoin('groups', 'users.groupId', '=', 'groups.id')
            ->where('users.id', $baseUser->id)
            ->first([
                'users.*',
                'groups.groupname as groupname',
            ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        // 4. Sla de info op in de sessie
        $request->session()->put('ss_user', [
            'id'        => $user->userID,
            'principal_type' => 'staff',
            'voornaam'  => $user->name,
            'naam'      => $user->surname,
            'gebruikersnaam' => $user->username,
            'platform'  => $user->platform,
            'groupId'        => $dbUser->groupId ?? null,
            'groupname'      => $dbUser->groupname ?? null,
        ]);

        return redirect('/'); // redirect na login
    }

    // 🔹 Logout
    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
