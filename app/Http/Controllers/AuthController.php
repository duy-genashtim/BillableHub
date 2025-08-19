<?php
namespace App\Http\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'access_token' => 'required|string',
            ]);

            // Get user info from Microsoft Graph API
            $response = Http::withToken($request->access_token)
                ->get('https://graph.microsoft.com/v1.0/me');

            if (! $response->successful()) {
                return response()->json(['error' => 'Invalid access token'], 401);
            }

            $azureUser = $response->json();

            // Get user photo
            $avatar = $this->saveAvatarToStorage($request->access_token, $azureUser['mail'] ?? $azureUser['userPrincipalName']);

            // Find or create user
            $user = User::updateOrCreate(
                ['email' => $azureUser['mail'] ?? $azureUser['userPrincipalName']],
                [
                    'name'              => $azureUser['displayName'],
                    'azure_id'          => $azureUser['id'],
                    'avatar'            => $avatar,
                    'azure_data'        => $azureUser,
                    'password'          => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                ]
            );

            // Generate JWT token
            $payload = [
                'iss' => config('app.url'),
                'sub' => $user->id,
                'iat' => time(),
                'exp' => time() + (20 * 24 * 60 * 60), // 7*24 hours
            ];

            $token = JWT::encode($payload, config('app.key'), 'HS256');

            // Load user with roles and permissions
            $user->load(['roles', 'permissions']);

            $permissionsConfig = config('constants.permissions');

            return response()->json([
                'user'  => [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'avatar'         => $avatar ? asset('storage/' . $avatar) : null,
                    'roles'          => $user->roles->map(function ($role) {
                        return [
                            'name'         => $role->name,
                            'display_name' => $role->display_name ?? strtoupper(str_replace('_', ' ', $role->name)),
                        ];
                    }),
                    'permissions'    => $user->getAllPermissions()->map(function ($permission) use ($permissionsConfig) {
                        return [
                            'name'         => $permission->name,
                            'display_name' => $permissionsConfig[$permission->name] ?? $permission->name,
                        ];
                    }),
                    'is_super_admin' => $user->isSuperAdmin(),
                ],
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    private function saveAvatarToStorage($accessToken, $email)
    {
        // Create avatars directory if it doesn't exist
        if (! Storage::disk('public')->exists('avatars')) {
            Storage::disk('public')->makeDirectory('avatars');
        }

        // Generate filename using email
        $filename = emailToFileName($email) . '.jpg';
        $filePath = 'avatars/' . $filename;

        // Try to get photo from Microsoft Graph API
        try {
            $photoResponse = Http::withToken($accessToken)
                ->timeout(15)
                ->get('https://graph.microsoft.com/v1.0/me/photo/$value');

            if ($photoResponse->successful() && ! empty($photoResponse->body())) {
                $imageData = $photoResponse->body();

                // Validate image data
                if ($this->isValidImageData($imageData)) {
                    // Save avatar (this will overwrite existing file with same name)
                    if (Storage::disk('public')->put($filePath, $imageData)) {
                        return $filePath;
                    }
                }
            }
        } catch (\Exception $e) {
            // Continue without avatar if photo retrieval fails
        }

        return null;
    }

    private function isValidImageData($imageData)
    {
        if (strlen($imageData) < 100) {
            return false;
        }

        $signatures = [
            "\xFF\xD8\xFF"     => 'jpg',
            "\x89\x50\x4E\x47" => 'png',
            "GIF87a"           => 'gif',
            "GIF89a"           => 'gif',
            "RIFF"             => 'webp',
        ];

        foreach ($signatures as $signature => $type) {
            if (strpos($imageData, $signature) === 0) {
                if ($type === 'webp' && strpos($imageData, 'WEBP') !== 8) {
                    continue;
                }
                return true;
            }
        }

        return false;
    }

    public function me(Request $request)
    {
        try {
            $user = $request->user();

            // Load user with roles and permissions
            // $user->load(['roles', 'permissions']);
            $user->load([
                'roles:id,name',
                'permissions:id,name',
            ]);

            $permissionsConfig = config('constants.permissions', []);

            $roles = $user->roles->map(function ($role) {
                return [
                    'name'         => $role->name,
                    'display_name' => $role->name, //strtoupper(str_replace('_', ' ', $role->name)),
                ];
            });

            $allPermissions = $this->getAllUserPermissions($user);
            $permissions    = $allPermissions->map(function ($permission) use ($permissionsConfig) {
                return [
                    'name'         => $permission->name,
                    'display_name' => $permissionsConfig[$permission->name] ?? $permission->name,
                ];
            });

            $avatarUrl = $user->avatar ? asset('storage/' . $user->avatar) : null;

            return response()->json([
                'user' => [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'avatar'         => $avatarUrl,
                    'roles'          => $roles,
                    'permissions'    => $permissions,
                    'is_super_admin' => $user->isSuperAdmin(),
                ],
            ]);

            // return response()->json([
            //     'user' => [
            //         'id'             => $user->id,
            //         'name'           => $user->name,
            //         'email'          => $user->email,
            //         'avatar'         => $user->avatar ? asset('storage/' . $user->avatar) : null,
            //         'roles'          => $user->roles->map(function ($role) {
            //             return [
            //                 'name'         => $role->name,
            //                 'display_name' => $role->display_name ?? $role->name,
            //             ];
            //         }),
            //         'permissions'    => $user->getAllPermissions()->map(function ($permission) {
            //             $displayName = config("constants.permissions.{$permission->name}", $permission->name);
            //             return [
            //                 'name'         => $permission->name,
            //                 'display_name' => $displayName,
            //             ];
            //         }),
            //         'is_super_admin' => $user->isSuperAdmin(),
            //     ],
            // ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'User not found'], 404);
        }
    }

    public function logout(Request $request)
    {
        // JWT is stateless, so we just return success
        return response()->json(['message' => 'Successfully logged out']);
    }

    private function getAllUserPermissions($user)
    {
        // Get direct permissions
        $directPermissions = $user->permissions;

        // Get role-based permissions efficiently
        $rolePermissions = collect();
        if ($user->roles->isNotEmpty()) {
            $roleIds         = $user->roles->pluck('id');
            $rolePermissions = Permission::whereIn('id', function ($query) use ($roleIds) {
                $query->select('permission_id')
                    ->from('role_has_permissions')
                    ->whereIn('role_id', $roleIds);
            })->get();
        }

        // Merge and remove duplicates
        return $directPermissions->merge($rolePermissions)->unique('id');
    }
}
