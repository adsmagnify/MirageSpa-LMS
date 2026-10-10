import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { ApiService } from './api.service';

export const authGuard: CanActivateFn = async (route) => {
  const api = inject(ApiService);
  const router = inject(Router);
  await api.hydrate();
  const url = router.getCurrentNavigation()?.extractedUrl.toString() || '/';
  if (route.data['auth'] && !api.user) {
    return router.createUrlTree(['/login'], { queryParams: { next: url } });
  }
  if (route.data['admin'] && !api.isAdmin()) return router.createUrlTree(['/']);
  if (route.data['staff'] && !api.isStaff()) return router.createUrlTree(['/']);
  if (route.data['teach'] && !api.canTeach()) return router.createUrlTree(['/']);
  if (api.user?.role === 'student' && url.startsWith('/courses/new')) return router.createUrlTree(['/']);
  return true;
};

export const guestGuard: CanActivateFn = async (route) => {
  const api = inject(ApiService);
  const router = inject(Router);
  await api.hydrate();
  if (api.user && !route.queryParamMap.get('next')) return router.createUrlTree(['/']);
  return true;
};
