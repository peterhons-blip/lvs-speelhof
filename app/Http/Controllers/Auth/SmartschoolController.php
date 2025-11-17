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

    public function redirect()
    {
        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'userinfo',
        ]);

        return redirect("{$this->oauthUrl}/OAuth?{$query}");
    }


    // 🔹 Callback van Smartschool
    public function callback(Request $request)
    {
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

        //  DB-check + groepnaam ophalen (LEFT JOIN op groups)
        $dbUser = User::query()
            ->leftJoin('groups', 'users.groupId', '=', 'groups.id')
            ->where('users.gebruikersnaam', $user->username)
            ->where('users.allowed_login', 1)
            ->first([
                'users.*',
                'groups.groupname as groupname',
            ]);

        if (!$dbUser) {
            return redirect('/')->with('error', 'Je hebt geen toegang. Neem contact op met de beheerder.');
        }


        // 4. Sla de info op in de sessie
        $request->session()->put('ss_user', [
            'id'        => $user->userID,
            'voornaam'  => $user->name,
            'naam'      => $user->surname,
            'gebruikersnaam' => $user->username,
            'platform'  => $user->platform,
            'groupId'        => $dbUser->groupId ?? null,
            'groupname'      => $dbUser->groupname ?? null,
        ]);

        return redirect('/leerlingen'); // redirect na login
    }

    // 🔹 Logout
    public function logout(Request $request)
    {
        $request->session()->forget('ss_user');
        return redirect('/');
    }
}
