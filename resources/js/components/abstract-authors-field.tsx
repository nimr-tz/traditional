import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Trash2 } from 'lucide-react';

export interface Author {
    name: string;
    institution: string;
    is_presenter: boolean;
    [key: string]: string | boolean;
}

interface AbstractAuthorsFieldProps {
    authors: Author[];
    onChange: (authors: Author[]) => void;
    errors?: Record<string, string | undefined>;
}

export default function AbstractAuthorsField({ authors, onChange, errors }: AbstractAuthorsFieldProps) {
    const update = (index: number, patch: Partial<Pick<Author, 'name' | 'institution'>>) => {
        onChange(authors.map((author, i) => (i === index ? { ...author, ...patch } : author)));
    };

    const setPresenter = (index: number) => {
        onChange(authors.map((author, i) => ({ ...author, is_presenter: i === index })));
    };

    const add = () => {
        onChange([...authors, { name: '', institution: '', is_presenter: authors.length === 0 }]);
    };

    const remove = (index: number) => {
        const next = authors.filter((_, i) => i !== index);
        if (next.length && !next.some((a) => a.is_presenter)) {
            next[0].is_presenter = true;
        }
        onChange(next);
    };

    return (
        <div className="grid gap-3">
            <Label>Authors (start with the presenting author)</Label>
            {authors.map((author, index) => (
                <div key={index} className="flex flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-start">
                    <div className="grid flex-1 gap-2 sm:grid-cols-2">
                        <Input placeholder="Author name" value={author.name} onChange={(e) => update(index, { name: e.target.value })} />
                        <Input
                            placeholder="Institution"
                            value={author.institution}
                            onChange={(e) => update(index, { institution: e.target.value })}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <label className="flex items-center gap-1 text-sm whitespace-nowrap">
                            <input type="radio" checked={author.is_presenter} onChange={() => setPresenter(index)} />
                            Presenter
                        </label>
                        <Button type="button" variant="ghost" size="icon" onClick={() => remove(index)} disabled={authors.length === 1}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            ))}
            {errors?.authors && <p className="text-destructive text-sm">{errors.authors}</p>}
            <Button type="button" variant="outline" size="sm" className="w-fit" onClick={add}>
                <Plus className="h-4 w-4" /> Add author
            </Button>
        </div>
    );
}
