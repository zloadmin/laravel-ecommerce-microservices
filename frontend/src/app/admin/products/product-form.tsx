"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { ApiError, describeError } from "@/lib/api/errors";
import type { Product, ProductInput } from "@/lib/api/types";
import { useCategories, useSaveProduct } from "@/lib/admin";
import { fromMinorUnits, toMinorUnits } from "@/lib/format";
import { useToast } from "@/components/toast";
import { Button, ButtonLink } from "@/components/ui/button";
import { Alert, Field, Input, Select, Textarea } from "@/components/ui/field";
import { Card } from "@/components/ui/misc";

const schema = z.object({
  name: z.string().trim().min(1, "Name is required.").max(255),
  slug: z
    .string()
    .trim()
    .regex(/^([a-z0-9]+(-[a-z0-9]+)*)?$/, "Use lowercase letters, numbers and single dashes."),
  sku: z.string().trim().min(1, "SKU is required.").max(64),
  description: z.string().max(10_000),
  price: z.string().trim().regex(/^\d+(\.\d{1,2})?$/, "Enter a price such as 19.99."),
  currency: z.string().trim().regex(/^[A-Z]{3}$/, "Use a three-letter code such as USD."),
  category_id: z.string(),
  stock: z
    .string()
    .trim()
    .regex(/^\d+$/, "Enter a whole number.")
    .refine((value) => Number(value) <= 1_000_000, "At most 1,000,000."),
  is_active: z.boolean(),
});

type Values = z.infer<typeof schema>;

/** API field names that differ from the form's. */
const FIELD_ALIASES: Record<string, keyof Values> = { price_amount: "price" };

export function ProductForm({ product }: { product?: Product }) {
  const router = useRouter();
  const toast = useToast();
  const categories = useCategories();
  const save = useSaveProduct(product?.id);

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: product?.name ?? "",
      slug: product?.slug ?? "",
      sku: product?.sku ?? "",
      description: product?.description ?? "",
      price: product ? fromMinorUnits(product.price.amount, product.price.currency) : "",
      currency: product?.price.currency ?? "USD",
      category_id: product?.category?.id ?? "",
      stock: String(product?.stock ?? 0),
      is_active: product?.is_active ?? true,
    },
  });
  const { register, handleSubmit, setError, formState } = form;
  const errors = formState.errors;

  const onSubmit = (values: Values) => {
    const input: Partial<ProductInput> = {
      name: values.name,
      sku: values.sku,
      description: values.description.trim() || null,
      price_amount: toMinorUnits(values.price, values.currency) ?? 0,
      currency: values.currency,
      category_id: values.category_id || null,
      stock: Number(values.stock),
      is_active: values.is_active,
    };
    // An empty slug on create lets the API derive it from the name; on edit it keeps the current one.
    if (values.slug) input.slug = values.slug;

    save.mutate(input, {
      onSuccess: (saved) => {
        toast.success(product ? "Product saved." : `Created “${saved.name}”.`);
        router.push("/admin/products");
      },
      onError: (error) => {
        if (error instanceof ApiError) {
          for (const [field, messages] of Object.entries(error.fields)) {
            const name = FIELD_ALIASES[field] ?? (field as keyof Values);
            if (name in schema.shape) setError(name, { message: messages[0] });
          }
        }
      },
    });
  };

  const apiError = save.error && !(save.error instanceof ApiError && save.error.code === "validation_failed") ? describeError(save.error) : null;

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-6">
      {apiError && <Alert>{apiError}</Alert>}
      {save.error instanceof ApiError && save.error.code === "validation_failed" && <Alert>Please correct the highlighted fields.</Alert>}

      <Card className="grid gap-5 p-6 sm:grid-cols-2">
        <Field label="Name" htmlFor="name" error={errors.name?.message} className="sm:col-span-2">
          <Input id="name" {...register("name")} aria-invalid={!!errors.name} />
        </Field>
        <Field label="Slug" htmlFor="slug" error={errors.slug?.message} hint={product ? undefined : "Leave empty to generate it from the name."}>
          <Input id="slug" {...register("slug")} aria-invalid={!!errors.slug} className="font-mono" />
        </Field>
        <Field label="SKU" htmlFor="sku" error={errors.sku?.message}>
          <Input id="sku" {...register("sku")} aria-invalid={!!errors.sku} className="font-mono" />
        </Field>
        <Field label="Description" htmlFor="description" error={errors.description?.message} className="sm:col-span-2">
          <Textarea id="description" rows={5} {...register("description")} aria-invalid={!!errors.description} />
        </Field>
      </Card>

      <Card className="grid gap-5 p-6 sm:grid-cols-2">
        <Field label="Price" htmlFor="price" error={errors.price?.message}>
          <Input id="price" inputMode="decimal" placeholder="0.00" {...register("price")} aria-invalid={!!errors.price} />
        </Field>
        <Field label="Currency" htmlFor="currency" error={errors.currency?.message}>
          <Input id="currency" maxLength={3} {...register("currency")} aria-invalid={!!errors.currency} className="uppercase" />
        </Field>
        <Field label="Stock" htmlFor="stock" error={errors.stock?.message}>
          <Input id="stock" inputMode="numeric" {...register("stock")} aria-invalid={!!errors.stock} />
        </Field>
        <Field label="Category" htmlFor="category_id" error={errors.category_id?.message}>
          <Select id="category_id" {...register("category_id")} disabled={categories.isPending}>
            <option value="">No category</option>
            {categories.data?.map((category) => (
              <option key={category.id} value={category.id}>
                {category.name}
              </option>
            ))}
          </Select>
        </Field>
        <label className="flex items-center gap-3 text-sm text-fg sm:col-span-2">
          <input type="checkbox" {...register("is_active")} className="size-4 rounded accent-accent" />
          Active — visible to shoppers
        </label>
      </Card>

      <div className="flex justify-end gap-3">
        <ButtonLink href="/admin/products" variant="secondary">
          Cancel
        </ButtonLink>
        <Button type="submit" loading={save.isPending}>
          {product ? "Save changes" : "Create product"}
        </Button>
      </div>
    </form>
  );
}
