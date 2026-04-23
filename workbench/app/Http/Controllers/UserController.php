<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Enum\AppStatus;
use Foundry\Foundry;
use Foundry\Jobs\ProcessCsvImport;
use Foundry\Models\File;
use Foundry\Models\Import;
use Foundry\Models\Subscription;
use Foundry\Models\User;
use Foundry\Notifications\UserResetPasswordNotification;
use Foundry\Services\Admin\SubscriptionService;
use Foundry\Traits\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use League\Csv\Reader;

class UserController extends Controller
{
    use Helpers;

    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Foundry::$userModel, 'user', [
            'except' => ['show', 'update', 'destroy', 'restore'],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', [Foundry::$userModel]);

        $user = Foundry::$userModel::with($request->includes ?? []);
        $isCancelled = $request->filled('type') && $request->type == 'cancelled';

        if ($request->filled('month') || $request->filled('year')) {
            $column = $isCancelled ? 'expires_at' : 'created_at';
            $user->whereDateColumn([
                'month' => $request->month,
                'year' => $request->year,
                'day' => $request->day,
            ], $column);
        }

        if ($request->filled('filter')) {
            if (str($request->filter)->startsWith('email:')) {
                $filter = str($request->filter)->replace('email:', '');
                $user->where('email', 'like', "{$filter}%");
            } else {
                $user->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'like', "%{$request->filter}%");
            }
        }

        if ($request->boolean('isEnquiry')) {
            $user->onlyEnquiry();
            if ($request->filled('status')) {
                $user->where('status', $request->input('status'));
            }
        } elseif ($request->boolean('option')) {
            $user->whereIn('status', [AppStatus::ACTIVE, AppStatus::PENDING]);
        } elseif ($isCancelled) {
            $user->onlyCancelled();
        } else {
            $user->onlyMember();

            if ($request->boolean('status')) {
                $user->onlyActive();
            }

            if ($request->filled('type')) {
                $user->whereTyped($request->input('type'));
            }
        }

        if ($request->filled('rag')) {
            $user->where('rag', $request->rag);
        }

        if ($request->boolean('deleted')) {
            $user->onlyTrashed();
        }

        $users = $user->sortBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?? 15);

        // Load subscription information for each user
        $users->getCollection()->transform(function ($user) {
            return $this->loadSubscription($user, []);
        });

        return new ResourceCollection($users);
    }

    /**
     * Display a options listing of the resource.
     */
    public function options(Request $request)
    {
        $request->merge([
            'option' => true,
        ]);

        return $this->index($request);
    }

    /**
     * Display a listing of the resource by ids.
     */
    public function listByIds(Request $request)
    {
        return Foundry::$userModel::whereIn('id', $request->ids)->get();
    }

    /**
     * Display a enquiry listing of the resource.
     */
    public function enquiry(Request $request)
    {
        $this->authorize('enquiry', [Foundry::$userModel]);

        $request->merge([
            'isEnquiry' => true,
        ]);

        return $this->index($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', [Foundry::$userModel]);

        $rules = [
            'email' => 'required|email|unique:users',
            'first_name' => 'required',
            'last_name' => 'required',
            'password' => 'confirmed',
            'address.line1' => 'required',
            'address.city' => 'required',
            'address.postal_code' => 'required',
            'address.country' => 'required',
        ];

        // Add plan validation if plan is provided
        if ($request->filled('plan')) {
            $rules['plan'] = 'exists:plans,id';
            $rules['payment_method'] = 'required|exists:payment_methods,id';
        }

        // Validate those rules
        $this->validate($request, $rules);

        $request->merge([
            'password' => bcrypt($request->password ?? str()->random(6)),
        ]);

        // create the user
        $user = Foundry::$userModel::create($request->input());

        // add address to the user
        $user = $user->updateOrCreateAddress($request->input('address'));

        if ($request->filled('avatar')) {
            $user->avatar()->sync([
                $request->input('avatar.id') => [
                    'type' => 'avatar',
                ],
            ]);
        }

        // Create subscription if plan is provided
        if ($request->filled('plan')) {
            $this->createSubscription($user, $request);
        }

        // Load subscription information - refresh user with relationships
        $user = $this->loadSubscription($user->fresh(['address', 'notes', 'subscriptions']));

        return response()->json([
            'data' => $user,
            'message' => __('User account has been created successfully!'),
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $user = Foundry::$userModel::withTrashed()->findOrFail($id);

        $this->authorize('view', [$user]);

        // Load subscription information
        $user = $this->loadSubscription($user->load(['notes']));

        return response()->json($user, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = Foundry::$userModel::withTrashed()->findOrFail($id);

        $this->authorize('update', [$user]);

        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => "email|unique:users,email,{$user->id}",
            'password' => 'confirmed',
            'address.line1' => 'required',
            'address.city' => 'required',
            'address.postal_code' => 'required',
            'address.country' => 'required',
        ];

        // Validate those rules
        $this->validate($request, $rules);

        if ($request->filled('password')) {
            $request->merge([
                'password' => bcrypt($request->password),
            ]);
        }

        $user->update($request->input());

        if ($request->filled('avatar')) {
            $user->avatar()->sync([
                $request->input('avatar.id') => [
                    'type' => 'avatar',
                ],
            ]);
        }

        if ($request->filled('special_note')) {
            $user->notes()->create([
                'type' => 'notes',
                'message' => $request->special_note,
            ]);
        }

        if ($request->filled('expires_at')) {
            $user = $user->updateExpiresAt($request->expires_at);
        }

        $user->updateOrCreateAddress($request->input('address'));

        // Load subscription information
        $user = $this->loadSubscription($user->fresh(['address', 'notes']));

        return response()->json([
            'data' => $user,
            'message' => __('User account has been updated successfully!'),
        ], 200);
    }

    /**
     * Change active of specified resource from storage.
     */
    public function changeActive(Request $request, $id)
    {
        $user = Foundry::$userModel::findOrFail($id);

        $this->authorize('update', [$user]);

        // Use forceFill to bypass $fillable — is_active is an admin-only field
        // intentionally excluded from mass-assignment.
        $user->forceFill(['is_active' => ! $user->is_active])->save();

        $type = ! $user->is_active ? 'archived' : 'unarchive';

        return response()->json([
            'message' => __('User account marked as :type successfully!', ['type' => __($type)]),
        ], 200);
    }

    /**
     * Create notes for specified resource from storage.
     */
    public function notes(Request $request, $id)
    {
        $user = Foundry::$userModel::findOrFail($id);

        $this->authorize('update', [$user]);

        if ($request->filled('rag')) {
            $user->update($request->only('rag'));
        }

        if ($request->filled('message')) {
            $request->merge([
                'type' => 'notes',
            ]);

            $note = $user->notes()->create($request->input());

            return response()->json([
                'data' => $note->load('admin'),
                'message' => __('Note has been added successfully!'),
            ], 200);
        } else {
            return response()->json([
                'data' => null,
                'message' => __('Note has been added successfully!'),
            ], 200);
        }
    }

    public function markAsPaid(Request $request, User $user)
    {
        $request->validate([
            'payment_method' => 'required|exists:payment_methods,id',
        ]);

        try {
            $subscription = $user->subscription();
            $subscription->pay($request->payment_method);
        } catch (\Throwable $e) {
            throw $e;
        }

        return response()->json([
            'data' => $this->loadSubscription($user->fresh()),
            'message' => __('Due payment has been received.'),
        ], 200);
    }

    /**
     * Send a password reset request to the specified user.
     */
    public function resetPasswordRequest(Request $request, $id)
    {
        $user = Foundry::$userModel::findOrFail($id);

        $this->authorize('update', [$user]);

        // Create password reset token
        $token = Password::createToken($user);

        // Build a generic reset URL (frontend may handle it). If frontend URL isn't configured, fallback to app URL
        $baseUrl = config('app.frontend_url') ?: config('app.url');
        $resetUrl = rtrim($baseUrl, '/').'/password/reset?token='.$token.'&email='.urlencode($user->email);

        // Send notification using common template system
        $user->notify(new UserResetPasswordNotification($user, [
            'token' => $token,
            'url' => $resetUrl,
            'expires' => config('auth.passwords.users.expire', 60),
        ]));

        return response()->json([
            'message' => __('Password reset email has been sent.'),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = Foundry::$userModel::withTrashed()->findOrFail($id);

        $this->authorize('delete', [$user]);

        if ($user->trashed()) {
            $user->forceDelete();
        } else {
            $user->delete();
        }

        return response()->json([
            'message' => __('User has been deleted successfully!'),
        ], 200);
    }

    /**
     * Remove the selected resources from storage.
     */
    public function destroySelected(Request $request)
    {
        $this->authorize('deleteAny', [Foundry::$userModel]);

        $request->validate([
            'items' => 'required|array',
        ]);

        $query = Foundry::$userModel::withTrashed()->whereIn('id', $request->items);

        if ($request->boolean('force')) {
            $query->forceDelete();
        } else {
            $query->delete();
        }

        return response()->json([
            'message' => __('Selected users have been deleted successfully!'),
        ], 200);
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore($id)
    {
        $user = Foundry::$userModel::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', [$user]);

        $user->restore();

        return response()->json([
            'message' => __('User has been restored successfully!'),
        ], 200);
    }

    /**
     * Restore the selected resources from storage.
     */
    public function restoreSelected(Request $request)
    {
        $this->authorize('restoreAny', [Foundry::$userModel]);

        $request->validate([
            'items' => 'required|array',
        ]);

        Foundry::$userModel::onlyTrashed()->whereIn('id', $request->items)->restore();

        return response()->json([
            'message' => __('Selected users have been restored successfully!'),
        ], 200);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|exists:files,id',
        ]);

        $file = File::findOrFail($request->file);
        $path = $file->path(); // file path of csv

        $expectedHeaders = [
            'First Name' => true,
            'Surname' => true,
            'Gender' => true,
            'Email Address' => true,
            'Phone Number' => false,
            'Status' => true,
            'Deactivates At' => false,
            'Password' => true,
            'Created At' => true,
            'Plan' => true,
            'Trial Ends At' => true,
            'Address Line1' => true,
            'Address Line2' => false,
            'Country' => true,
            'State' => true,
            'State Code' => true,
            'City' => true,
            'Postcode/Zip' => false,
            'Note' => false,
        ];

        // Read CSV headers
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $csv->setDelimiter(',');

        // Normalize CSV headers to remove newlines
        $csvHeaders = array_map('trim', $csv->getHeader());
        $mappedHeaders = Foundry::$userModel::getMappedAttributes();

        // Map $headers from $mapped
        $finalHeaders = [];
        foreach ($csvHeaders as $header) {
            if (isset($mappedHeaders[$header])) {
                $finalHeaders[] = $mappedHeaders[$header];
            } else {
                $finalHeaders[] = $header;
            }
        }

        // Validate unwanted fields
        $unwantedFields = array_diff($csvHeaders, array_keys($expectedHeaders));
        if (! empty($unwantedFields)) {
            throw ValidationException::withMessages([
                'unwanted' => [__('Unwanted CSV headers: :headers', ['headers' => implode(', ', $unwantedFields)])],
            ]);
        }

        // Validate required headers
        $requiredHeaders = array_keys(array_filter($expectedHeaders));
        $missingHeaders = array_diff($requiredHeaders, $csvHeaders);
        if (! empty($missingHeaders)) {
            throw ValidationException::withMessages([
                'required' => [__('Missing a required header: :headers', ['headers' => implode(', ', $missingHeaders)])],
            ]);
        }

        $rows = array_values([...$csv->getRecords($finalHeaders)]);

        // Normalize status values to lowercase for enum validation
        foreach ($rows as &$row) {
            if (isset($row['status']) && is_string($row['status'])) {
                $row['status'] = strtolower($row['status']);
            }
        }
        unset($row); // Break the reference

        $this->validate(new Request(['rows' => $rows]), [
            'rows.*.status' => new Enum(AppStatus::class),
            'rows.*.email' => 'email',
            'rows.*.created_at' => 'date_format:Y-m-d H:i:s',
            'rows.*.deactivates_at' => 'date_format:Y-m-d H:i:s',
            'rows.*.trial_ends_at' => 'date_format:Y-m-d H:i:s',
            'rows.*.plan' => 'nullable|string',
        ]);

        $import = Import::create([
            'model' => Foundry::$userModel,
            'file_id' => $file->id,
            'options' => $request->input(),
        ]);

        ProcessCsvImport::dispatch($import);

        // Return response indicating the import process has started
        return response()->json([
            'message' => __('This could take some time to complete. You can close this dialog box while we upload your file. We will email you once the import finishes.'),
        ], 200);
    }

    /**
     * Load subscription information for the user.
     *
     * @param  \App\Models\User  $user
     * @return \App\Models\User
     */
    protected function loadSubscription($user, array $extends = ['usages', 'plan', 'next_plan'])
    {
        /** @var Subscription $subscription */
        $subscription = $user->subscription('default');

        $user['subscription'] = $subscription?->toResponse($extends);

        $user->unsetRelation('subscriptions');

        return $user;
    }

    /**
     * Create subscription for the user during store operation.
     *
     * @param  User  $user
     * @return Subscription
     */
    protected function createSubscription($user, Request $request)
    {
        $service = app(SubscriptionService::class);

        try {
            $subscription = $service->createOrUpdate($user, [
                'plan' => $request->plan,
                'promotion_code' => $request->promotion_code ?? null,
                'payment_method' => $request->payment_method ?? null,
                'trial_days' => $request->trail_days ?? null,
            ]);

            return $subscription;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'subscription' => __('Failed to create subscription: :message', [
                    'message' => $e->getMessage(),
                ]),
            ]);
        }
    }
}
