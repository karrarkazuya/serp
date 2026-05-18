<?php

namespace App\Http\Controllers\Api\Contacts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Models\Contacts\Contact;
use App\Services\Contacts\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contactService) {}

    public function read(Request $request): JsonResponse
    {
        $query = Contact::query();

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($request->get('filter') === 'inactive') {
            $query->inactive();
        } elseif ($request->get('filter') !== 'all') {
            $query->active();
        }

        if ($type = $request->get('type')) {
            $query->where('contact_type', $type);
        }

        return response()->json(
            $query->orderBy('name')->paginate($request->integer('per_page', 24))
        );
    }

    public function show(Contact $contact): JsonResponse
    {
        return response()->json($contact->load(['creator', 'updater']));
    }

    public function create(StoreContactRequest $request): JsonResponse
    {
        $contact = DB::transaction(fn () => $this->contactService->create($request->validated()));

        return response()->json([
            'message' => 'Contact created successfully.',
            'data'    => $contact,
        ], 201);
    }

    public function write(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        $contact = DB::transaction(fn () => $this->contactService->update($contact, $request->validated()));

        return response()->json([
            'message' => 'Contact updated successfully.',
            'data'    => $contact,
        ]);
    }

    public function unlink(Request $request, Contact $contact): JsonResponse
    {
        DB::transaction(fn () => $this->contactService->delete($contact));

        return response()->json(['message' => 'Contact deleted.']);
    }

    public function archive(Contact $contact): JsonResponse
    {
        $contact = DB::transaction(fn () => $this->contactService->archive($contact));
        return response()->json(['message' => 'Contact archived.', 'data' => $contact]);
    }

    public function chatter(Contact $contact): JsonResponse
    {
        $messages = $contact->chatterMessages()->with('user')->get();
        return response()->json($messages);
    }
}
