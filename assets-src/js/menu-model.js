export function menuItems(permissions) {
  const has = (code) => permissions.includes(code);
  const items = [{ href: '/', label: 'Nuevo paste' }];

  if (has('paste.list_own')) items.push({ href: '/mine', label: 'Mis pastes' });
  if (has('tag.manage_own')) items.push({ href: '/app/tags', label: 'Tags' });
  if (has('report.review')) items.push({ href: '/app/moderation', label: 'Moderación' });
  if (has('user.manage') || has('role.manage')) items.push({ href: '/app/admin', label: 'Administración' });
  if (has('audit.read')) items.push({ href: '/app/audit', label: 'Auditoría' });

  return items;
}