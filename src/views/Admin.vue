<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Admin</h1>
        <p class="dek">Administrators run the school: accounts, the school name, and anything sitting in trash.</p>
      </div>
    </header>
    <div class="widgets">
      <router-link class="card" to="/users"><h2>Users</h2><p class="dek">Create accounts and assign Administrator, Instructor, or Learner.</p></router-link>
      <router-link class="card" to="/schools"><h2>School</h2><p class="dek">Set the name that appears on the home desk.</p></router-link>
      <router-link class="card" to="/trash"><h2>Trash</h2><p class="dek">Restore or permanently remove courses and resources.</p></router-link>
      <router-link class="card" to="/reports"><h2>Reports</h2><p class="dek">See learners, completions, and work left to score.</p></router-link>
    </div>
  </div>
</template>
