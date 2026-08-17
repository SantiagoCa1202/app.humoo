import AsyncStorage from "@react-native-async-storage/async-storage";
import { getLocales } from "expo-localization";
import i18n from "i18next";
import { initReactI18next } from "react-i18next";

import commonEn from "@/i18n/locales/en/common.json";
import purchasingReceivingEn from "@/i18n/locales/en/purchasing-receiving.json";
import documentsEn from "@/i18n/locales/en/documents.json";
import authEn from "@/i18n/locales/en/auth.json";
import appEn from "@/i18n/locales/en/app.json";
import commonEs from "@/i18n/locales/es/common.json";
import purchasingReceivingEs from "@/i18n/locales/es/purchasing-receiving.json";
import documentsEs from "@/i18n/locales/es/documents.json";
import authEs from "@/i18n/locales/es/auth.json";
import appEs from "@/i18n/locales/es/app.json";

const STORAGE_KEY = "humoo.locale";
const defaultLanguage = getLocales()[0]?.languageCode === "es" ? "es" : "en";

i18n.use(initReactI18next).init({
  compatibilityJSON: "v4",
  lng: defaultLanguage,
  fallbackLng: "en",
  interpolation: { escapeValue: false },
  resources: {
    en: { common: { ...commonEn, ...purchasingReceivingEn, ...documentsEn }, auth: authEn, app: appEn },
    es: { common: { ...commonEs, ...purchasingReceivingEs, ...documentsEs }, auth: authEs, app: appEs },
  },
  defaultNS: "common",
});

export async function hydrateStoredLanguage() {
  const stored = await AsyncStorage.getItem(STORAGE_KEY);
  if (stored === "en" || stored === "es") await i18n.changeLanguage(stored);
}

export async function setPreferredLanguage(language: "en" | "es") {
  await AsyncStorage.setItem(STORAGE_KEY, language);
  await i18n.changeLanguage(language);
}

export default i18n;
