<template>
  <!-- Same shape as ApplicantMatchCard, shown while the first page loads. -->
  <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5" aria-hidden="true">
    <div class="flex justify-between">
      <div class="flex flex-col gap-2">
        <Skeleton width="10rem" height="1.1rem" />
        <Skeleton width="6rem" height="0.8rem" />
        <Skeleton width="5.5rem" height="1.4rem" border-radius="1rem" />
      </div>
      <Skeleton shape="circle" size="2.5rem" />
    </div>
    <Skeleton height="0.8rem" />
    <Skeleton width="85%" height="0.8rem" />
    <Skeleton width="70%" height="0.8rem" />
    <div class="flex gap-2">
      <Skeleton height="1.9rem" />
      <Skeleton height="1.9rem" />
      <Skeleton height="1.9rem" />
    </div>
    <Skeleton width="6rem" height="1rem" />
  </div>
</template>
