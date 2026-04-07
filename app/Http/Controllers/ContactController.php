<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Services\InputSanitizationException;
use App\Services\InputSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ContactController extends Controller
{
    public function __construct(
        private readonly InputSanitizer $sanitizer,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        try {
            $validated = $this->sanitizer->sanitizeFields($validated, [
                'name' => 180,
                'subject' => 255,
                'message' => 3000,
            ]);
        } catch (InputSanitizationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ContactMessage::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Message sent successfully.'], 201);
    }
}
