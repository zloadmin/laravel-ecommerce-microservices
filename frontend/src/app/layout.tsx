import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { currentUser } from "@/lib/api/server";
import { SiteHeader } from "@/components/site-header";
import { Providers } from "./providers";
import "./globals.css";

const geistSans = Geist({ variable: "--font-geist-sans", subsets: ["latin"] });
const geistMono = Geist_Mono({ variable: "--font-geist-mono", subsets: ["latin"] });

export const metadata: Metadata = {
  title: { default: "Storefront", template: "%s · Storefront" },
  description: "Shop laptops, phones and accessories.",
};

export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const user = await currentUser();

  return (
    <html lang="en">
      <body className={`${geistSans.variable} ${geistMono.variable} flex min-h-screen flex-col font-sans antialiased`}>
        <Providers key={user?.id ?? "guest"}>
          <SiteHeader user={user} />
          <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:py-10">{children}</main>
          <footer className="border-t border-line py-8 text-center text-sm text-fg-subtle">
            Storefront demo · built on the e-commerce microservices API
          </footer>
        </Providers>
      </body>
    </html>
  );
}
