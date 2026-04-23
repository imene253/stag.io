<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\CompanyApprovedNotification;
use App\Notifications\CompanyRegistrationPendingApprovalNotification;
use App\Notifications\CompanyRejectedNotification;
use App\Notifications\StudentApprovedNotification;
use App\Notifications\StudentRegistrationPendingApprovalNotification;
use App\Notifications\StudentRejectedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *   path="/api/register",
     *   tags={"Auth"},
     *   summary="Register a new user",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","password","password_confirmation","role"},
     *       @OA\Property(property="name", type="string"),
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="password", type="string"),
     *       @OA\Property(property="password_confirmation", type="string"),
     *       @OA\Property(property="role", type="string", example="student")
     *     )
     *   ),
     *   @OA\Response(response=201, description="Registration successful")
     * )
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role'     => ['required', 'in:student,company'], 
        ]);

        if ($request->role === 'student' && ! $this->isUniversityEmail($request->email)) {
            return response()->json([
                'message' => 'Validation error.',
                'errors' => [
                    'email' => ['Students must register with a university email address.'],
                ],
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => $request->role,
            'is_active'=> false,
        ]);

       
        if ($user->isStudent()) {
            $user->studentProfile()->create([
                'user_id'  => $user->id,
                'full_name'=> $request->name,
                'email'    => $request->email,
            ]);

            // Notify admins a new student requires approval.
            User::where('role', 'admin')->get()->each(function (User $admin) use ($user): void {
                $admin->notify(
                    new StudentRegistrationPendingApprovalNotification($user)
                );
            });
        } else {
            $user->companyProfile()->create([
                'user_id'      => $user->id,
                'company_name' => $request->name,
            ]);

            // Notify admins a new company requires approval.
            User::where('role', 'admin')->get()->each(function (User $admin) use ($user): void {
                $admin->notify(
                    new CompanyRegistrationPendingApprovalNotification($user)
                );
            });
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $relation = $user->isStudent() ? 'studentProfile' : 'companyProfile';

        return response()->json([
            'message' => $user->isCompany()
                ? 'Registration successful. Your company account is pending admin approval.'
                : 'Registration successful. Your student account is pending admin approval.',
            'token'   => $token,
            'role'    => $user->role,
            'user'    => $user->load($relation),
        ], 201);
    }

    /**
     * @OA\Post(
     *   path="/api/login",
     *   tags={"Auth"},
     *   summary="Login and receive an API token",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","password"},
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="password", type="string")
     *     )
     *   ),
     *   @OA\Response(response=200, description="Login successful")
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            $message = match ($user->role) {
                'student' => 'Your student account is pending admin approval.',
                'company' => 'Your company account is pending admin approval.',
                default => 'Your account is disabled. Contact administration.',
            };

            return response()->json([
                'message' => $message,
            ], 403);
        }

      
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        $relation = match($user->role) {
            'student' => 'studentProfile',
            'company' => 'companyProfile',
            default   => null,
        };

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'role'    => $user->role,
            'user'    => $relation ? $user->load($relation) : $user,
        ]);
    }

    /**
     * Create an admin user (utility endpoint for seeding / setup).
     *
     * NOTE: This is intentionally left unauthenticated for your current
     * environment so you can create admins from Swagger. In a real
     * production environment, you should:
     * - Protect this with admin-only auth, OR
     * - Remove this endpoint completely after initial setup.
     *
     * @OA\Post(
     *   path="/api/create-admin",
     *   tags={"Auth"},
     *   summary="Create an admin user (utility endpoint)",
     *   description="Creates a new admin user with the provided name, email, and password. Intended for initial setup via Swagger UI.",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","password"},
     *       @OA\Property(property="name", type="string"),
     *       @OA\Property(property="email", type="string", format="email"),
     *       @OA\Property(property="password", type="string")
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Admin created successfully"
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error (e.g. email already taken)"
     *   )
     * )
     */
    public function createAdmin(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Admin created successfully',
            'user'    => $user,
        ], 201);
    }

    /**
     * @OA\Post(
     *   path="/api/logout",
     *   tags={"Auth"},
     *   summary="Revoke current token (logout)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Logged out")
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/me",
     *   tags={"Auth"},
     *   summary="Get current authenticated user",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Current user")
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $relation = match($user->role) {
            'student' => 'studentProfile',
            'company' => 'companyProfile',
            default   => null,
        };

        return response()->json([
            'user' => $relation ? $user->load($relation) : $user,
            'role' => $user->role,
        ]);
    }

    public function pendingCompanies()
    {
        $companies = User::query()
            ->where('role', 'company')
            ->where('is_active', false)
            ->with('companyProfile')
            ->latest()
            ->paginate(15);

        return response()->json($companies);
    }

    public function pendingStudents()
    {
        $students = User::query()
            ->where('role', 'student')
            ->where('is_active', false)
            ->with('studentProfile')
            ->latest()
            ->paginate(15);

        return response()->json($students);
    }

    public function approveCompany(string $id)
    {
        $company = User::query()
            ->where('id', $id)
            ->where('role', 'company')
            ->first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found.',
            ], 404);
        }

        $company->update(['is_active' => true]);
        $company->notify(new CompanyApprovedNotification($company));

        return response()->json([
            'message' => 'Company approved successfully.',
            'user' => $company->fresh()->load('companyProfile'),
        ]);
    }

    public function rejectCompany(string $id)
    {
        $company = User::query()
            ->where('id', $id)
            ->where('role', 'company')
            ->first();

        if (! $company) {
            return response()->json([
                'message' => 'Company not found.',
            ], 404);
        }

        $company->update(['is_active' => false]);
        $company->notify(new CompanyRejectedNotification($company));

        return response()->json([
            'message' => 'Company set to pending/inactive.',
            'user' => $company->fresh()->load('companyProfile'),
        ]);
    }

    public function approveStudent(string $id)
    {
        $student = User::query()
            ->where('id', $id)
            ->where('role', 'student')
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'Student not found.',
            ], 404);
        }

        $student->update(['is_active' => true]);
        $student->notify(new StudentApprovedNotification($student));

        return response()->json([
            'message' => 'Student approved successfully.',
            'user' => $student->fresh()->load('studentProfile'),
        ]);
    }

    public function rejectStudent(string $id)
    {
        $student = User::query()
            ->where('id', $id)
            ->where('role', 'student')
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'Student not found.',
            ], 404);
        }

        $student->update(['is_active' => false]);
        $student->notify(new StudentRejectedNotification($student));

        return response()->json([
            'message' => 'Student set to pending/inactive.',
            'user' => $student->fresh()->load('studentProfile'),
        ]);
    }

    private function isUniversityEmail(string $email): bool
    {
        $domain = Str::lower(Str::after($email, '@'));

        if ($domain === '' || ! Str::contains($email, '@')) {
            return false;
        }

        $allowedDomains = array_values(array_filter(array_map(
            static fn (string $value): string => Str::lower(trim($value)),
            explode(',', (string) env('UNIVERSITY_EMAIL_DOMAINS', ''))
        )));

        if (empty($allowedDomains)) {
            return Str::endsWith($domain, '.edu');
        }

        foreach ($allowedDomains as $allowedDomain) {
            if ($domain === $allowedDomain || Str::endsWith($domain, '.'.$allowedDomain)) {
                return true;
            }
        }

        return false;
    }
}