import { Redirect } from "expo-router";
import { routes } from "@/navigation/routes";

export default function AppIndexRoute() {
  return <Redirect href={routes.app.chat} />;
}
