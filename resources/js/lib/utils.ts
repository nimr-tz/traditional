import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** "Dr. Jane Doe" — pairs with backend records that send `name` + `salutation` separately. */
export function formatPersonName(person: { name: string; salutation?: string | null }): string {
    return person.salutation ? `${person.salutation} ${person.name}` : person.name;
}
