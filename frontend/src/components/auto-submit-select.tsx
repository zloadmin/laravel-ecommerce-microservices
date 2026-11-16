"use client";

import type { ComponentProps } from "react";
import { Select } from "./ui/field";

/** A select that submits its form as soon as the value changes (e.g. sort order). */
export function AutoSubmitSelect(props: ComponentProps<"select">) {
  return <Select {...props} onChange={(event) => event.currentTarget.form?.requestSubmit()} />;
}
