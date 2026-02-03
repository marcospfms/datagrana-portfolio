# Configuração Google OAuth 2.0 - DataGrana

Guia completo de configuração do Google OAuth 2.0 do zero para o ecossistema DataGrana (Laravel backend + Expo mobile app).

**Última atualização:** 2026-01-30

---

## Índice

1. [Pré-requisitos](#pré-requisitos)
2. [Parte 1: Google Cloud Console](#parte-1-google-cloud-console)
3. [Parte 2: Configuração Laravel (datagrana-portfolio)](#parte-2-configuração-laravel-datagrana-portfolio)
4. [Parte 3: Configuração Expo App (datagrana-app)](#parte-3-configuração-expo-app-datagrana-app)
5. [Parte 4: Testes e Verificação](#parte-4-testes-e-verificação)
6. [Troubleshooting](#troubleshooting)
7. [Referências](#referências)

---

## Pré-requisitos

Antes de começar, certifique-se de ter:

- [ ] Conta Google ativa (Gmail)
- [ ] Acesso ao [Google Cloud Console](https://console.cloud.google.com)
- [ ] Projeto Laravel configurado e rodando
- [ ] Expo App configurado com `app.json` válido
- [ ] Bundle ID (iOS) e Package Name (Android) definidos

**Informações necessárias:**

| Item | Exemplo | Onde encontrar |
|------|---------|----------------|
| **Bundle ID (iOS)** | `com.mkto.datagranaapp` | `app.json` → `expo.ios.bundleIdentifier` |
| **Package Name (Android)** | `com.mkto.datagranaapp` | `app.json` → `expo.android.package` |
| **Expo Username** | `mkto` | `app.json` → `expo.owner` |
| **App Slug** | `datagrana-app` | `app.json` → `expo.slug` |
| **Laravel App URL (Dev)** | `http://localhost:8000` | `.env` → `APP_URL` |
| **Laravel App URL (Prod)** | `https://api.datagrana.app` | `.env.production` → `APP_URL` |

---

## Parte 1: Google Cloud Console

### 1.1. Criar ou Selecionar Projeto

#### Passo 1: Acessar Google Cloud Console
1. Acesse: https://console.cloud.google.com
2. Faça login com sua conta Google

#### Passo 2: Criar Novo Projeto
1. No topo da página, clique no seletor de projetos
2. Clique em **"NEW PROJECT"** (NOVO PROJETO)
3. Preencha os dados:
   - **Project name:** `DataGrana`
   - **Project ID:** `datagrana-app` (ou deixe o Google gerar)
   - **Organization:** (deixe em branco se não tiver)
4. Clique em **"CREATE"** (CRIAR)
5. Aguarde a criação (pode levar alguns segundos)
6. Selecione o projeto criado no seletor de projetos

---

### 1.2. Configurar OAuth Consent Screen

#### Passo 1: Acessar OAuth Consent Screen
1. No menu lateral, vá em: **APIs & Services** → **OAuth consent screen**
2. Ou acesse diretamente: https://console.cloud.google.com/apis/credentials/consent

#### Passo 2: Configurar User Type
1. Selecione **"External"** (uso externo)
2. Clique em **"CREATE"** (CRIAR)

#### Passo 3: Configurar App Information
Preencha os campos obrigatórios:

| Campo | Valor |
|-------|-------|
| **App name** | `DataGrana` |
| **User support email** | Seu email Gmail |
| **App logo** | (opcional) Upload do logo 120x120px |
| **Application home page** | `https://datagrana.app` |
| **Application privacy policy link** | `https://datagrana.app/privacy` |
| **Application terms of service link** | `https://datagrana.app/terms` |
| **Authorized domains** | `datagrana.app` |
| **Developer contact information** | Seu email Gmail |

Clique em **"SAVE AND CONTINUE"** (SALVAR E CONTINUAR)

#### Passo 4: Configurar Scopes
1. Clique em **"ADD OR REMOVE SCOPES"**
2. Marque os seguintes scopes:
   - ✅ `.../auth/userinfo.email` - Ver o endereço de email principal da Conta do Google
   - ✅ `.../auth/userinfo.profile` - Ver suas informações pessoais
   - ✅ `openid` - Autenticar usando OpenID Connect
3. Clique em **"UPDATE"** (ATUALIZAR)
4. Clique em **"SAVE AND CONTINUE"**

#### Passo 5: Adicionar Test Users
1. Clique em **"ADD USERS"** (ADICIONAR USUÁRIOS)
2. Adicione os emails que poderão testar o app:
   ```
   marcospaulo.20@gmail.com
   seu-email@gmail.com
   ```
3. Clique em **"ADD"** (ADICIONAR)
4. Clique em **"SAVE AND CONTINUE"**

#### Passo 6: Revisar e Confirmar
1. Revise todas as configurações
2. Clique em **"BACK TO DASHBOARD"** (VOLTAR PARA O PAINEL)

**Status:** O app ficará em modo "Testing" até ser publicado

---

### 1.3. Criar Credenciais OAuth 2.0

Você precisa criar **4 tipos diferentes de Client IDs**:
1. Web Application (para Laravel)
2. iOS Application (para Expo iOS)
3. Android Application (para Expo Android)
4. Web Application (para Expo Web/Development)

---

#### 1.3.1. Web Application (Laravel Backend)

#### Passo 1: Iniciar Criação
1. Vá em: **APIs & Services** → **Credentials**
2. Clique em **"+ CREATE CREDENTIALS"** → **"OAuth client ID"**

#### Passo 2: Configurar
| Campo | Valor |
|-------|-------|
| **Application type** | Web application |
| **Name** | `DataGrana Web (Laravel)` |

#### Passo 3: Authorized JavaScript origins
Adicione as URLs base da sua aplicação:

**Desenvolvimento:**
```
http://localhost:8000
http://127.0.0.1:8000
```

**Produção:**
```
https://api.datagrana.app
https://datagrana.app
```

#### Passo 4: Authorized redirect URIs
Adicione as URLs de callback:

**Desenvolvimento:**
```
http://localhost:8000/auth/google/callback
http://127.0.0.1:8000/auth/google/callback
```

**Produção:**
```
https://api.datagrana.app/auth/google/callback
https://datagrana.app/auth/google/callback
```

#### Passo 5: Criar
1. Clique em **"CREATE"** (CRIAR)
2. **IMPORTANTE:** Copie e salve:
   - ✅ **Client ID:** `123456789-xxxxxxxxxx.apps.googleusercontent.com`
   - ✅ **Client Secret:** `GOCSPX-xxxxxxxxxxxxxxxxxxxx`
3. Clique em **"OK"**

**Guardar em:** `.env` do Laravel como `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET`

---

#### 1.3.2. iOS Application (Expo iOS)

#### Passo 1: Obter Bundle ID
No seu `app.json`, localize:
```json
{
  "expo": {
    "ios": {
      "bundleIdentifier": "com.mkto.datagranaapp"
    }
  }
}
```

Se não existir, defina um Bundle ID único.

#### Passo 2: Criar Credencial
1. Clique em **"+ CREATE CREDENTIALS"** → **"OAuth client ID"**
2. Selecione **Application type:** `iOS`
3. Preencha:

| Campo | Valor |
|-------|-------|
| **Name** | `DataGrana iOS (Expo)` |
| **Bundle ID** | `com.mkto.datagranaapp` (do app.json) |
| **App Store ID** | (deixe em branco por enquanto) |
| **Team ID** | (deixe em branco por enquanto) |

4. Clique em **"CREATE"**
5. **Copie o Client ID:** `123456789-ios-xxxxx.apps.googleusercontent.com`

**Guardar em:** `.env` do datagrana-app como `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID`

---

#### 1.3.3. Android Application (Expo Android)

#### Passo 1: Obter Package Name
No seu `app.json`, localize:
```json
{
  "expo": {
    "android": {
      "package": "com.mkto.datagranaapp"
    }
  }
}
```

Se não existir, defina um Package Name único.

#### Passo 2: Gerar SHA-1 Fingerprint

**Para Desenvolvimento Local:**
```bash
# Gerar keystore de desenvolvimento (se não existir)
keytool -genkey -v -keystore debug.keystore -alias androiddebugkey -keyalg RSA -keysize 2048 -validity 10000

# Extrair SHA-1
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android
```

**Para Expo Development Build:**
```bash
# Usando EAS
eas credentials

# Ou gere um SHA-1 temporário para testes
openssl rand -base64 32 | openssl sha1 -c
```

**Para Produção (Google Play):**
1. Acesse: [Google Play Console](https://play.google.com/console)
2. Selecione seu app
3. Vá em: **Release** → **Setup** → **App Integrity**
4. Copie o **SHA-1 certificate fingerprint** em:
   - **Upload key certificate** (para desenvolvimento)
   - **App signing key certificate** (para produção)

Copie o SHA-1 (exemplo): `A1:B2:C3:D4:E5:F6:...`

#### Passo 3: Criar Credencial
1. Clique em **"+ CREATE CREDENTIALS"** → **"OAuth client ID"**
2. Selecione **Application type:** `Android`
3. Preencha:

| Campo | Valor |
|-------|-------|
| **Name** | `DataGrana Android (Expo)` |
| **Package name** | `com.mkto.datagranaapp` (do app.json) |
| **SHA-1 certificate fingerprint** | `A1:B2:C3:D4:E5:F6:...` (gerado acima) |

4. Clique em **"CREATE"**
5. **Copie o Client ID:** `123456789-android-xxxxx.apps.googleusercontent.com`

**Guardar em:** `.env` do datagrana-app como `EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID`

---

#### 1.3.4. Web Application (Expo Web/Auth Proxy)

Esta credencial é usada pelo `expo-auth-session` para o fluxo de autenticação via proxy.

#### Passo 1: Obter Informações do Expo
```json
{
  "expo": {
    "owner": "mkto",
    "slug": "datagrana-app"
  }
}
```

Seu **redirect URI** será: `https://auth.expo.io/@mkto/datagrana-app`

#### Passo 2: Criar Credencial
1. Clique em **"+ CREATE CREDENTIALS"** → **"OAuth client ID"**
2. Selecione **Application type:** `Web application`
3. Preencha:

| Campo | Valor |
|-------|-------|
| **Name** | `DataGrana Expo Web` |

#### Passo 3: Authorized JavaScript origins
```
https://auth.expo.io
http://localhost:19006
http://localhost:8081
```

#### Passo 4: Authorized redirect URIs
```
https://auth.expo.io/@mkto/datagrana-app
http://localhost:19006
http://localhost:8081
```

5. Clique em **"CREATE"**
6. **Copie o Client ID:** `123456789-web-xxxxx.apps.googleusercontent.com`

**Guardar em:** `.env` do datagrana-app como `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID`

---

### 1.4. Resumo das Credenciais

Após criar todas as credenciais, você deve ter:

| Tipo | Nome | Client ID | Usado em |
|------|------|-----------|----------|
| Web | DataGrana Web (Laravel) | `xxx-web.apps.googleusercontent.com` | Laravel backend |
| iOS | DataGrana iOS (Expo) | `xxx-ios.apps.googleusercontent.com` | Expo iOS |
| Android | DataGrana Android (Expo) | `xxx-android.apps.googleusercontent.com` | Expo Android |
| Web | DataGrana Expo Web | `xxx-expo.apps.googleusercontent.com` | Expo development |

**Também copie:**
- ✅ Client Secret (apenas do Web Application Laravel)

---

## Parte 2: Configuração Laravel (datagrana-portfolio)

### 2.1. Instalar Laravel Socialite

#### Passo 1: Verificar se já está instalado
```bash
cd datagrana-portfolio
composer show laravel/socialite
```

Se não estiver instalado:
```bash
composer require laravel/socialite
```

#### Passo 2: Publicar configuração (opcional)
```bash
php artisan vendor:publish --provider="Laravel\Socialite\SocialiteServiceProvider"
```

---

### 2.2. Configurar Variáveis de Ambiente

#### Passo 1: Editar `.env`
```bash
# Google OAuth (usado para validar id_token no login)
GOOGLE_CLIENT_ID=123456789-web-xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxx
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

#### Passo 2: Editar `.env.production` (se existir)
```bash
GOOGLE_CLIENT_ID=123456789-web-xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxx
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

**Importante:**
- Use o **Client ID e Secret do "Web Application (Laravel)"** criado no passo 1.3.1
- Certifique-se que `APP_URL` está correto
- Não compartilhe o `GOOGLE_CLIENT_SECRET` publicamente

---

### 2.3. Configurar Services

#### Arquivo: `config/services.php`

Verifique se a configuração do Google está presente:

```php
<?php

return [
    // ... outras configurações

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
];
```

---

### 2.4. Configurar Rotas

#### Arquivo: `routes/auth.php` ou `routes/web.php`

```php
<?php

use App\Http\Controllers\Auth\SocialAuthController;

Route::middleware('guest')->group(function () {
    // Rota de redirecionamento para Google
    Route::get('auth/google/redirect', [SocialAuthController::class, 'redirect'])
        ->name('auth.google.redirect');

    // Rota de callback do Google
    Route::get('auth/google/callback', [SocialAuthController::class, 'callback'])
        ->name('auth.google.callback');
});
```

---

### 2.5. Criar ou Verificar Controller

#### Arquivo: `app/Http/Controllers/Auth/SocialAuthController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Handle the OAuth callback from Google.
     */
    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('login')
                ->with('status', 'Não foi possível autenticar com sua conta Google. Tente novamente.');
        }

        if (!$googleUser->getEmail()) {
            return redirect()
                ->route('login')
                ->with('status', 'Não foi possível obter o email da sua conta Google.');
        }

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (!$user) {
            // Criar novo usuário
            $user = User::create([
                'google_id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName() ?? 'Usuário',
                'photo' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'status' => true,
                'password' => Hash::make(Str::random(32)),
            ]);
        } else {
            // Atualizar usuário existente
            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'photo' => $googleUser->getAvatar() ?? $user->photo,
                'name' => $googleUser->getName() ?? $user->name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        // Criar token Sanctum para API
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;
        session(['auth_token' => $token]);

        return Inertia::location(route('dashboard', absolute: false));
    }
}
```

---

### 2.6. Configurar Model User

#### Arquivo: `app/Models/User.php`

Certifique-se que o model User tem a coluna `google_id`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'photo',
        'status',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'status' => 'boolean',
    ];
}
```

---

### 2.7. Migration (se necessário)

Se a coluna `google_id` não existir:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('id');
            $table->string('photo')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'photo']);
        });
    }
};
```

Execute:
```bash
php artisan migrate
```

---

### 2.8. Testar Laravel OAuth

#### Teste via navegador:
1. Acesse: `http://localhost:8000/auth/google/redirect`
2. Você deve ser redirecionado para a tela de consentimento do Google
3. Após autorizar, deve ser redirecionado de volta para: `http://localhost:8000/auth/google/callback`
4. Deve fazer login e redirecionar para o dashboard

#### Verificar logs:
```bash
tail -f storage/logs/laravel.log
```

---

## Parte 3: Configuração Expo App (datagrana-app)

### 3.1. Instalar Dependências

#### Passo 1: Verificar dependências no `package.json`
```bash
cd datagrana-app
cat package.json | grep expo-auth-session
```

Deve conter:
```json
{
  "dependencies": {
    "expo-auth-session": "~7.0.10",
    "expo-web-browser": "~15.0.10"
  }
}
```

#### Passo 2: Instalar se necessário
```bash
npx expo install expo-auth-session expo-web-browser
```

---

### 3.2. Configurar app.json

#### Arquivo: `app.json`

```json
{
  "expo": {
    "name": "Datagrana App",
    "slug": "datagrana-app",
    "owner": "mkto",
    "scheme": "datagranaapp",
    "version": "1.0.0",
    "ios": {
      "bundleIdentifier": "com.mkto.datagranaapp",
      "supportsTablet": true
    },
    "android": {
      "package": "com.mkto.datagranaapp",
      "adaptiveIcon": {
        "backgroundColor": "#E6F4FE"
      }
    }
  }
}
```

**Importante:**
- ✅ `scheme` deve ser único e sem espaços
- ✅ `owner` deve ser seu username Expo
- ✅ `ios.bundleIdentifier` deve ser único (formato reverse domain)
- ✅ `android.package` deve ser único (formato reverse domain)

---

### 3.3. Configurar Variáveis de Ambiente

#### Arquivo: `.env`

```bash
# DataGrana App - Variáveis de Ambiente

# URL da API
EXPO_PUBLIC_API_URL=http://10.10.1.15:8000/api

# Ambiente
APP_ENV=development

# Google OAuth (obrigatório para login Google)
# Use as credenciais criadas no Google Cloud Console

# iOS Client ID (do passo 1.3.2)
EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID=123456789-ios-xxxxx.apps.googleusercontent.com

# Android Client ID (do passo 1.3.3)
EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID=123456789-android-xxxxx.apps.googleusercontent.com

# Web Client ID (do passo 1.3.4)
EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID=123456789-web-xxxxx.apps.googleusercontent.com

# Expo Client ID (mesmo que Web Client ID)
EXPO_PUBLIC_GOOGLE_EXPO_CLIENT_ID=123456789-web-xxxxx.apps.googleusercontent.com
```

**Importante:**
- Use os Client IDs específicos criados nos passos 1.3.2, 1.3.3 e 1.3.4
- **NÃO** use o mesmo Client ID para todas as plataformas
- Substitua `10.10.1.15` pelo IP da sua máquina na rede local

---

### 3.4. Verificar Implementação do Login

#### Arquivo: `app/(auth)/login.tsx`

O código já está implementado corretamente:

```typescript
import * as Google from 'expo-auth-session/providers/google';
import { makeRedirectUri } from 'expo-auth-session';

// ...

const scheme = useMemo(() => {
    const rawScheme = Constants.expoConfig?.scheme ?? 'datagranaapp';
    return Array.isArray(rawScheme) ? rawScheme[0] : rawScheme;
}, []);

const redirectUri = useMemo(
    () =>
        makeRedirectUri({
            scheme,
        }),
    [scheme],
);

const iosClientId =
    process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID ??
    process.env.EXPO_PUBLIC_GOOGLE_EXPO_CLIENT_ID;

const androidClientId =
    process.env.EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID ??
    process.env.EXPO_PUBLIC_GOOGLE_EXPO_CLIENT_ID;

const [request, response, promptAsync] = Google.useIdTokenAuthRequest({
    clientId: process.env.EXPO_PUBLIC_GOOGLE_EXPO_CLIENT_ID,
    iosClientId,
    androidClientId,
    webClientId: process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID,
    scopes: ['profile', 'email'],
    redirectUri,
});
```

---

### 3.5. Verificar Auth Service

#### Arquivo: `features/auth/services/auth.ts`

```typescript
export class AuthService {
  static async login(payload: GoogleLoginPayload): Promise<AuthResponse> {
    const response = await api.post<AuthResponse>('/auth/google', payload);

    if (!response?.token) {
      throw new Error('API não retornou o token de autenticação.');
    }

    await SecureStorage.setItem(TOKEN_KEY, response.token);
    await SecureStorage.setItem(USER_KEY, JSON.stringify(response.user));

    return response;
  }
}
```

**Endpoint:** `/auth/google` (Laravel backend)

---

### 3.6. Criar Endpoint Laravel para Mobile

#### Arquivo: `routes/api.php`

```php
<?php

use App\Http\Controllers\Auth\AuthController;

Route::post('/auth/google', [AuthController::class, 'google'])
    ->name('auth.google.mobile');
```

#### Arquivo: `app/Http/Controllers/Auth/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        protected GoogleAuthService $googleAuthService
    ) {}

    public function google(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Token inválido',
            ], 422);
        }

        $googleData = $this->googleAuthService->verifyIdToken(
            $request->input('id_token')
        );

        if (!$googleData) {
            return response()->json([
                'success' => false,
                'error' => 'Falha ao verificar token do Google',
            ], 401);
        }

        $user = $this->googleAuthService->findOrCreateUser($googleData);

        // Revogar tokens anteriores
        $user->tokens()->delete();

        // Criar novo token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'photo' => $user->photo,
            ],
        ]);
    }
}
```

---

### 3.7. Google Auth Service (Laravel)

#### Arquivo: `app/Services/Auth/GoogleAuthService.php`

Este arquivo já existe e está correto:

```php
<?php

namespace App\Services\Auth;

use App\Models\User;
use Google_Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthService
{
    protected Google_Client $client;

    public function __construct()
    {
        $this->client = new Google_Client([
            'client_id' => config('services.google.client_id'),
        ]);
    }

    public function verifyIdToken(string $idToken): ?array
    {
        try {
            $payload = $this->client->verifyIdToken($idToken);

            if (!$payload) {
                Log::warning('Google token verification returned empty payload.');
                return null;
            }

            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'] ?? null,
                'photo' => $payload['picture'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
            ];
        } catch (\Throwable $exception) {
            Log::error('Google token verification failed.', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    public function findOrCreateUser(array $googleData): User
    {
        $user = User::query()
            ->where('google_id', $googleData['google_id'])
            ->orWhere('email', $googleData['email'])
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleData['google_id'],
                'photo' => $googleData['photo'] ?? $user->photo,
                'name' => $googleData['name'] ?? $user->name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            Log::info('User updated via Google OAuth.', ['user_id' => $user->id]);
        } else {
            $user = User::create([
                'google_id' => $googleData['google_id'],
                'email' => $googleData['email'],
                'name' => $googleData['name'] ?? 'Usuario',
                'photo' => $googleData['photo'],
                'email_verified_at' => now(),
                'status' => true,
                'password' => Hash::make(Str::random(32)),
            ]);

            Log::info('New user created via Google OAuth.', ['user_id' => $user->id]);
        }

        return $user;
    }
}
```

---

### 3.8. Instalar Google API PHP Client (Laravel)

#### Passo 1: Verificar instalação
```bash
cd datagrana-portfolio
composer show google/apiclient
```

#### Passo 2: Instalar se necessário
```bash
composer require google/apiclient
```

---

## Parte 4: Testes e Verificação

### 4.1. Teste Laravel (Web)

#### Passo 1: Iniciar servidor
```bash
cd datagrana-portfolio
php artisan serve
```

#### Passo 2: Testar login
1. Abra: `http://localhost:8000/auth/google/redirect`
2. Autorize com sua conta Google
3. Verifique se é redirecionado para o dashboard
4. Verifique se o usuário foi criado no banco de dados

#### Passo 3: Verificar logs
```bash
tail -f storage/logs/laravel.log
```

---

### 4.2. Teste Expo App (Mobile)

#### Passo 1: Iniciar Expo
```bash
cd datagrana-app
npx expo start
```

#### Passo 2: Testar no dispositivo

**Opção A: Expo Go (limitações)**
- Expo Go tem limitações com OAuth
- Pode não funcionar corretamente
- Use apenas para testes iniciais

**Opção B: Development Build (recomendado)**
```bash
# Criar development build
npx expo run:ios
# ou
npx expo run:android
```

#### Passo 3: Realizar login
1. Abra o app
2. Clique em "Continuar com Google"
3. Autorize com sua conta Google
4. Verifique se o login foi bem-sucedido

#### Passo 4: Verificar logs
```bash
# Expo logs
npx expo start --dev-client

# Laravel logs
tail -f datagrana-portfolio/storage/logs/laravel.log
```

---

### 4.3. Checklist de Verificação

#### Google Cloud Console
- [ ] Projeto criado e selecionado
- [ ] OAuth Consent Screen configurado
- [ ] Test users adicionados
- [ ] 4 Client IDs criados (Web Laravel, iOS, Android, Web Expo)
- [ ] Redirect URIs configurados corretamente
- [ ] Scopes configurados (openid, email, profile)

#### Laravel (datagrana-portfolio)
- [ ] Laravel Socialite instalado
- [ ] Google API PHP Client instalado
- [ ] `.env` configurado com Client ID e Secret
- [ ] `config/services.php` configurado
- [ ] Rotas criadas (`auth/google/redirect` e `callback`)
- [ ] Controller `SocialAuthController` criado
- [ ] Endpoint API `/auth/google` criado para mobile
- [ ] Service `GoogleAuthService` criado
- [ ] Migration executada (coluna `google_id`)
- [ ] Teste web funcionando

#### Expo App (datagrana-app)
- [ ] `expo-auth-session` instalado
- [ ] `app.json` configurado (scheme, bundleIdentifier, package)
- [ ] `.env` configurado com 3 Client IDs
- [x] Android Client ID configurado
- [ ] Código de login implementado
- [ ] Auth Service configurado
- [ ] Development Build criado
- [x] Teste mobile funcionando (Android)

---

## Troubleshooting

### Erro: "Error 400: invalid_request"

**Causa:** Redirect URI não configurado corretamente

**Solução:**
1. Verifique se o redirect URI no código corresponde exatamente ao configurado no Google Console
2. Aguarde 5-10 minutos após alterar as configurações no Google Console
3. Limpe o cache do navegador/app

---

### Erro: "doesn't comply with Google's OAuth 2.0 policy"

**Causa:** Client ID incorreto ou fluxo OAuth desatualizado

**Solução:**
1. Certifique-se de usar Client IDs separados para cada plataforma
2. Para Android, verifique se o SHA-1 está correto
3. Para iOS, verifique se o Bundle ID está correto
4. Use Development Build em vez de Expo Go

---

### Erro: "redirect_uri_mismatch"

**Causa:** Redirect URI não corresponde

**Solução:**
1. No Google Console, verifique os "Authorized redirect URIs"
2. No código, verifique qual redirect URI está sendo usado:
   ```typescript
   console.log('Redirect URI:', redirectUri);
   ```
3. Adicione o redirect URI exato no Google Console
4. Aguarde 5-10 minutos para propagar

---

### Erro: "Access blocked: This app's request is invalid"

**Causa:** App não está em modo de produção e usuário não é test user

**Solução:**
1. Vá em OAuth Consent Screen → Test users
2. Adicione o email do usuário
3. Ou publique o app (solicitar verificação do Google)

---

### Expo App não abre o navegador

**Causa:** Expo Go não suporta OAuth corretamente

**Solução:**
1. Use Development Build:
   ```bash
   npx expo run:ios
   # ou
   npx expo run:android
   ```

---

### Token inválido no Laravel

**Causa:** Client ID no `.env` está incorreto

**Solução:**
1. Verifique se está usando o Client ID do "Web Application (Laravel)"
2. Não use o Client ID do iOS/Android no Laravel

---

### SHA-1 fingerprint inválido (Android)

**Causa:** SHA-1 configurado não corresponde ao usado para assinar o app

**Solução:**
1. Para desenvolvimento local:
   ```bash
   keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android
   ```
2. Para produção, use o SHA-1 do Google Play Console
3. Atualize o SHA-1 no Google Cloud Console
4. Aguarde 5-10 minutos

---

### Múltiplos Client IDs causam confusão

**Solução:**
Organize assim:

| Uso | Client ID | Onde usar |
|-----|-----------|-----------|
| Laravel Web | `xxx-web.apps.googleusercontent.com` | Laravel `.env` → `GOOGLE_CLIENT_ID` |
| Expo iOS | `xxx-ios.apps.googleusercontent.com` | Expo `.env` → `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID` |
| Expo Android | `xxx-android.apps.googleusercontent.com` | Expo `.env` → `EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID` |
| Expo Web/Dev | `xxx-expo.apps.googleusercontent.com` | Expo `.env` → `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID` |

---

## Referências

### Documentação Oficial

- [Google OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Google OAuth 2.0 for Mobile Apps](https://developers.google.com/identity/protocols/oauth2/native-app)
- [Laravel Socialite Documentation](https://laravel.com/docs/12.x/socialite)
- [Expo Authentication Guide](https://docs.expo.dev/guides/authentication/)
- [Expo AuthSession Documentation](https://docs.expo.dev/versions/latest/sdk/auth-session/)
- [Google API PHP Client](https://github.com/googleapis/google-api-php-client)

### Artigos e Tutoriais

- [Google OAuth Policy Compliance](https://developers.google.com/identity/protocols/oauth2/production-readiness/policy-compliance)
- [Setting up OAuth 2.0 - Google Cloud Console](https://support.google.com/cloud/answer/6158849)
- [Expo Google Sign-In Tutorial](https://medium.com/@gbenleseun2016/guide-to-sign-in-with-google-on-the-expo-platform-using-expo-auth-session-9d3688d2107a)

### Issues e Troubleshooting

- [expo/expo #22594 - redirect_uri_mismatch](https://github.com/expo/expo/issues/22594)
- [expo/expo #16650 - expo-auth-session in production](https://github.com/expo/expo/issues/16650)
- [expo/expo #28544 - invalid_request redirect_uri](https://github.com/expo/expo/issues/28544)

---

## Checklist Final

Antes de ir para produção:

- [ ] **OAuth Consent Screen em produção**
  - Solicitar verificação do Google
  - Aguardar aprovação (pode levar semanas)

- [ ] **Configurar domínios de produção**
  - Atualizar redirect URIs com domínios HTTPS
  - Adicionar domínios aos "Authorized domains"

- [ ] **Segurança**
  - Nunca expor `GOOGLE_CLIENT_SECRET` publicamente
  - Usar HTTPS em produção
  - Implementar rate limiting
  - Validar todos os tokens

- [ ] **Monitoramento**
  - Configurar logs de erro
  - Monitorar tentativas de login
  - Alertas para falhas de autenticação

- [ ] **Documentação**
  - Documentar fluxo de autenticação
  - Criar guia para novos desenvolvedores
  - Manter este documento atualizado

---

**Última atualização:** 2026-01-20
**Versão:** 1.0.0
**Autor:** DataGrana Team
