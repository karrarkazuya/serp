<?php

namespace App\Services\Contacts;

use App\Models\Contacts\Contact;
use App\Services\Chatter\ChatterService;

class ContactService
{
    public function __construct(
        private readonly ChatterService $chatterService
    ) {}

    public function create(array $data): Contact
    {
        $contact = Contact::create($data);

        $this->chatterService->logCreated($contact, 'Contact');

        return $contact;
    }

    public function update(Contact $contact, array $data): Contact
    {
        $changes = $this->detectChanges($contact, $data);

        $contact->update($data);

        if (!empty($changes)) {
            $this->chatterService->logUpdated($contact, $changes, 'Contact');
        }

        return $contact->fresh();
    }

    public function archive(Contact $contact): Contact
    {
        $contact->update(['active' => false]);
        $this->chatterService->logArchived($contact, 'Contact');

        return $contact;
    }

    public function unarchive(Contact $contact): Contact
    {
        $contact->update(['active' => true]);
        $this->chatterService->logUnarchived($contact, 'Contact');

        return $contact;
    }

    public function delete(Contact $contact): void
    {
        $this->chatterService->log($contact, 'Contact deleted.', 'system');
        $contact->delete();
    }

    private function detectChanges(Contact $contact, array $data): array
    {
        $changes = [];

        foreach ($contact->chatterTracked as $field => $label) {
            if (!array_key_exists($field, $data)) continue;

            $old = (string) ($contact->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');

            if ($old === $new) continue;

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $this->resolveValue($field, $contact->{$field}),
                'to'    => $this->resolveValue($field, $data[$field]),
            ];
        }

        return $changes;
    }

    private function resolveValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') return '—';

        return match ($field) {
            'company_id'   => \App\Models\Settings\Company::find($value)?->name ?? "#{$value}",
            'contact_type' => ucfirst((string) $value),
            default        => (string) $value,
        };
    }
}
