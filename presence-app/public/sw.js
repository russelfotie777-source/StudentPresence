/*
 * Service worker de Présence : reçoit les rappels de pointage poussés par
 * l'API (Web Push) et les affiche même quand l'app est fermée. Aucune
 * interception réseau : il ne fait que des notifications.
 */
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));

self.addEventListener("push", (event) => {
  let charge = {};
  try {
    charge = event.data ? event.data.json() : {};
  } catch {
    charge = { body: event.data ? event.data.text() : "" };
  }

  const titre = charge.title || "Présence";
  const options = {
    body: charge.body || "",
    icon: charge.icon || "/iut-douala.png",
    badge: charge.badge || "/iut-douala.png",
    tag: charge.tag,
    renotify: Boolean(charge.renotify),
    data: charge.data || { url: "/dashboard" },
    vibrate: [120, 60, 120],
  };

  event.waitUntil(self.registration.showNotification(titre, options));
});

// Un appui sur la notification ramène sur l'accueil (ou l'onglet déjà ouvert).
self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || "/dashboard";

  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((fenetres) => {
      for (const fenetre of fenetres) {
        if ("focus" in fenetre) {
          if ("navigate" in fenetre) fenetre.navigate(url);
          return fenetre.focus();
        }
      }
      return self.clients.openWindow(url);
    }),
  );
});
