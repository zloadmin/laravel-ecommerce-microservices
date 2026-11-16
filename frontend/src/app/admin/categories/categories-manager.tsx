"use client";

import { useState, type FormEvent } from "react";
import { ApiError, describeError } from "@/lib/api/errors";
import type { Category } from "@/lib/api/types";
import { useCategories, useDeleteCategory, useSaveCategory } from "@/lib/admin";
import { useToast } from "@/components/toast";
import { Button } from "@/components/ui/button";
import { Alert, Field, Input } from "@/components/ui/field";
import { Card, EmptyState, Skeleton } from "@/components/ui/misc";

function categoryError(error: unknown): string {
  if (error instanceof ApiError && error.code === "category_not_empty") {
    return "This category still has products. Move or delete them first.";
  }
  if (error instanceof ApiError && error.code === "validation_failed") {
    return Object.values(error.fields)[0]?.[0] ?? error.message;
  }
  return describeError(error);
}

export function CategoriesManager() {
  const { data: categories, isPending, error } = useCategories();

  return (
    <div className="grid gap-8 lg:grid-cols-[1fr_300px]">
      <div>
        {isPending ? (
          <Skeleton className="h-48" />
        ) : error ? (
          <Alert>{describeError(error)}</Alert>
        ) : categories.length === 0 ? (
          <EmptyState title="No categories yet" description="Add one with the form." />
        ) : (
          <Card className="divide-y divide-line">
            {categories.map((category) => (
              <CategoryRow key={category.id} category={category} />
            ))}
          </Card>
        )}
      </div>
      <CreateCategory />
    </div>
  );
}

function CategoryRow({ category }: { category: Category }) {
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(category.name);
  const [slug, setSlug] = useState(category.slug);
  const save = useSaveCategory();
  const remove = useDeleteCategory();
  const toast = useToast();

  if (editing) {
    const submit = (event: FormEvent) => {
      event.preventDefault();
      save.mutate(
        { id: category.id, input: { name: name.trim(), slug: slug.trim() } },
        {
          onSuccess: () => {
            setEditing(false);
            toast.success("Category saved.");
          },
          onError: (e) => toast.error(categoryError(e)),
        },
      );
    };
    return (
      <form onSubmit={submit} className="flex flex-wrap items-center gap-2 px-4 py-3">
        <Input value={name} onChange={(e) => setName(e.target.value)} aria-label="Name" className="w-auto flex-1" required />
        <Input value={slug} onChange={(e) => setSlug(e.target.value)} aria-label="Slug" className="w-auto flex-1 font-mono" required />
        <Button type="submit" size="sm" loading={save.isPending}>
          Save
        </Button>
        <Button size="sm" variant="ghost" onClick={() => setEditing(false)}>
          Cancel
        </Button>
      </form>
    );
  }

  return (
    <div className="flex items-center justify-between gap-4 px-4 py-3">
      <div>
        <p className="font-medium text-fg">{category.name}</p>
        <p className="font-mono text-xs text-fg-subtle">{category.slug}</p>
      </div>
      <div className="flex gap-3 text-sm">
        <button type="button" className="text-accent hover:underline" onClick={() => setEditing(true)}>
          Edit
        </button>
        <button
          type="button"
          className="text-fg-subtle hover:text-red-600 disabled:opacity-50"
          disabled={remove.isPending}
          onClick={() => {
            if (!confirm(`Delete the “${category.name}” category?`)) return;
            remove.mutate(category.id, {
              onSuccess: () => toast.success("Category deleted."),
              onError: (e) => toast.error(categoryError(e)),
            });
          }}
        >
          Delete
        </button>
      </div>
    </div>
  );
}

function CreateCategory() {
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const save = useSaveCategory();
  const toast = useToast();
  const fields = save.error instanceof ApiError ? save.error.fields : {};

  const submit = (event: FormEvent) => {
    event.preventDefault();
    save.mutate(
      { input: { name: name.trim(), ...(slug.trim() ? { slug: slug.trim() } : {}) } },
      {
        onSuccess: (category) => {
          toast.success(`Created “${category.name}”.`);
          setName("");
          setSlug("");
        },
      },
    );
  };

  return (
    <Card className="self-start p-5">
      <h2 className="mb-4 font-semibold text-fg">New category</h2>
      <form onSubmit={submit} className="space-y-4">
        {save.error && !Object.keys(fields).length && <Alert>{categoryError(save.error)}</Alert>}
        <Field label="Name" htmlFor="new-name" error={fields.name}>
          <Input id="new-name" value={name} onChange={(e) => setName(e.target.value)} required />
        </Field>
        <Field label="Slug" htmlFor="new-slug" error={fields.slug} hint="Optional — generated from the name.">
          <Input id="new-slug" value={slug} onChange={(e) => setSlug(e.target.value)} className="font-mono" />
        </Field>
        <Button type="submit" className="w-full" loading={save.isPending} disabled={!name.trim()}>
          Add category
        </Button>
      </form>
    </Card>
  );
}
