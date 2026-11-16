import type { components } from "./schema";

type Schemas = components["schemas"];

export type Money = Schemas["Money"];
export type User = Schemas["User"];
export type Role = User["role"];
export type Category = Schemas["Category"];
export type Product = Schemas["Product"];
export type ProductInput = Schemas["ProductInput"];
export type CategoryInput = Schemas["CategoryInput"];
export type Order = Schemas["Order"];
export type OrderItem = Order["items"][number];
export type OrderStatus = Schemas["OrderStatus"];
export type CursorMeta = Schemas["CursorMeta"];

type RawCartLine = Schemas["Cart"]["items"][number];
type AlwaysPresent = "product_id" | "quantity" | "available";

/** A cart line. Product fields are absent when the product was deleted or deactivated. */
export type CartLine = Pick<RawCartLine, AlwaysPresent> & Partial<Omit<RawCartLine, AlwaysPresent>>;

export type Cart = Omit<Schemas["Cart"], "items"> & { items: CartLine[] };

/** A login or registration result: the user and a fresh token pair. */
export type Session = {
  user: User;
  access_token: string;
  token_type: string;
  expires_in: number;
  refresh_token: string;
};

export type Page<T> = { data: T[]; meta: CursorMeta };
