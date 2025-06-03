<?php
namespace App\Http\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

            // Get user photo with multiple fallback attempts
            // $avatar = $this->getUserAvatar($request->access_token);
            $avatar = $this->saveAvatarToStorage($request->access_token, $azureUser['id']);
            // Find or create user
            $user = User::updateOrCreate(
                ['email' => $azureUser['mail'] ?? $azureUser['userPrincipalName']],
                [
                    'name'              => $azureUser['displayName'],
                    'azure_id'          => $azureUser['id'],
                    'avatar'            => $avatar,
                    'azure_data'        => $azureUser,
                    'password'          => Hash::make(Str::random(32)), // Random password for security
                    'email_verified_at' => now(),
                ]
            );

            // Generate JWT token
            $payload = [
                'iss' => config('app.url'),
                'sub' => $user->id,
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60), // 24 hours
            ];

            $token = JWT::encode($payload, config('app.key'), 'HS256');

            return response()->json([
                'user'  => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'avatar' => $avatar ? asset('storage/' . $avatar) : null, //$user->avatar,
                ],
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    private function getUserAvatar($accessToken)
    {
        // Try different photo endpoints with fallback
        $photoEndpoints = [
            'https://graph.microsoft.com/v1.0/me/photo/$value',          // Original size
            'https://graph.microsoft.com/v1.0/me/photos/120x120/$value', // Medium size
            'https://graph.microsoft.com/v1.0/me/photos/96x96/$value',   // Small size
            'https://graph.microsoft.com/v1.0/me/photos/48x48/$value',   // Tiny size
        ];

        foreach ($photoEndpoints as $endpoint) {
            try {
                $photoResponse = Http::withToken($accessToken)
                    ->timeout(10) // Set timeout to avoid hanging
                    ->get($endpoint);

                if ($photoResponse->successful() && ! empty($photoResponse->body())) {
                    // Check if it's a valid image by looking at content type or size
                    $contentType = $photoResponse->header('Content-Type');
                    $imageData   = $photoResponse->body();

                    // Basic validation - check if it looks like image data
                    if (strlen($imageData) > 100 && $this->isValidImageData($imageData, $contentType)) {
                        return 'data:' . ($contentType ?: 'image/jpeg') . ';base64,' . base64_encode($imageData);
                    }
                }
            } catch (\Exception $e) {
                // Continue to next endpoint if this one fails
                continue;
            }
        }

        // If all photo endpoints fail, try to get photo metadata to see if photo exists
        try {
            $photoMetaResponse = Http::withToken($accessToken)
                ->get('https://graph.microsoft.com/v1.0/me/photo');

            if ($photoMetaResponse->successful()) {
                // Photo exists but we couldn't fetch it - return null for now
                // Could implement alternative approach here (like using initials)
                return null;
            }
        } catch (\Exception $e) {
            // Photo doesn't exist or we can't access it
        }

        return null; // No photo available
    }

    private function saveAvatarToStorage($accessToken, $azureId)
    {
        // Create avatars directory if it doesn't exist
        if (! Storage::disk('public')->exists('avatars')) {
            Storage::disk('public')->makeDirectory('avatars');
        }

        // Try different photo endpoints
        $photoEndpoints = [
            'https://graph.microsoft.com/v1.0/me/photo/$value',
            'https://graph.microsoft.com/v1.0/me/photos/120x120/$value',
            'https://graph.microsoft.com/v1.0/me/photos/96x96/$value',
            'https://graph.microsoft.com/v1.0/me/photos/48x48/$value',
        ];

        foreach ($photoEndpoints as $endpoint) {
            try {
                $photoResponse = Http::withToken($accessToken)
                    ->timeout(15)
                    ->get($endpoint);

                if ($photoResponse->successful() && ! empty($photoResponse->body())) {
                    $imageData   = $photoResponse->body();
                    $contentType = $photoResponse->header('Content-Type');

                    // Validate image
                    if ($this->isValidImageData($imageData, $contentType)) {
                        // Get file extension
                        $extension = $this->getFileExtension($contentType);

                        // Create filename
                        $filename = 'avatar_' . $azureId . '_' . time() . '.' . $extension;
                        $filePath = 'avatars/' . $filename;

                        // Delete old avatar for this user
                        $this->deleteOldUserAvatar($azureId);

                        // Save new avatar
                        if (Storage::disk('public')->put($filePath, $imageData)) {
                            return $filePath; // Return: "avatars/avatar_123_456789.jpg"
                        }
                    }
                }
            } catch (\Exception $e) {
                continue; // Try next endpoint
            }
        }

        return null; // No avatar found
    }

// HELPER METHODS
    private function deleteOldUserAvatar($azureId)
    {
        try {
            $files = Storage::disk('public')->files('avatars');
            foreach ($files as $file) {
                if (strpos($file, 'avatar_' . $azureId . '_') !== false) {
                    Storage::disk('public')->delete($file);
                }
            }
        } catch (\Exception $e) {
            // Ignore deletion errors
        }
    }

    private function getFileExtension($contentType)
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        return $extensions[$contentType] ?? 'jpg';
    }

    private function isValidImageData($imageData, $contentType)
    {
        if (strlen($imageData) < 100) {
            return false;
        }

        $signatures = [
            "\xFF\xD8\xFF"     => 'jpg',  // JPEG
            "\x89\x50\x4E\x47" => 'png',  // PNG
            "GIF87a"           => 'gif',  // GIF
            "GIF89a"           => 'gif',  // GIF
            "RIFF"             => 'webp', // WEBP
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

            return response()->json([
                'user' => [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'User not found'], 404);
        }
    }

    public function logout(Request $request)
    {
        // JWT is stateless, so we just return success
        // In a production app, you might want to maintain a blacklist of tokens
        return response()->json(['message' => 'Successfully logged out']);
    }
}