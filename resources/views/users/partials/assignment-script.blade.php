@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const approvalModulesByDivision = @json($approvalModulesByDivision);
    const division = document.getElementById('divisionSelect');
    const department = document.getElementById('departmentSelect');
    const position = document.getElementById('positionSelect');
    const accessRole = document.getElementById('accessRoleSelect');
    const accessHelp = document.getElementById('accessRoleHelp');
    const vesselAssignments = document.getElementById('vesselAssignmentFields');
    const approvalAuthority = document.getElementById('approvalAuthorityFields');
    const approvalLabels = approvalAuthority ? [...approvalAuthority.querySelectorAll('[data-approval-module]')] : [];
    const syncApprovalAuthority = () => {
        if (!approvalAuthority || !division || !accessRole) return;
        const allowedModules = Object.keys(approvalModulesByDivision[division.value] || {});
        const isApprover = accessRole.selectedOptions[0]?.dataset.slug === 'company-approver';
        approvalAuthority.classList.toggle('hidden', !isApprover || allowedModules.length === 0);
        approvalLabels.forEach(label => {
            const allowed = isApprover && allowedModules.includes(label.dataset.approvalModule);
            label.classList.toggle('hidden', !allowed);
            label.classList.toggle('flex', allowed);
            const checkbox = label.querySelector('input');
            checkbox.disabled = !allowed;
            if (!allowed) checkbox.checked = false;
        });
    };
    if (division && department) {
        const departmentOptions = [...department.options];
        const positionOptions = position ? [...position.options] : [];
        const syncVesselAssignments = () => {
            if (!vesselAssignments) return;
            const companyName = division.selectedOptions[0]?.dataset.name || '';
            const departmentName = department.selectedOptions[0]?.dataset.name || '';
            const positionCode = position?.selectedOptions[0]?.dataset.code || '';
            const managerPositions = ['operations-manager', 'operation-manager', 'marine-operations-manager', 'vessel-manager', 'technical-manager'];
            const requiresAssignment = companyName === 'villa shipping lines'
                && ['marine operations', 'technical department'].includes(departmentName)
                && !managerPositions.includes(positionCode);
            vesselAssignments.classList.toggle('hidden', !requiresAssignment);
        };
        const filterPositions = () => {
            if (!position) return;
            positionOptions.forEach(option => { option.hidden = Boolean(option.value && (option.dataset.division !== division.value || option.dataset.department !== department.value)); });
            if (position.selectedOptions[0]?.hidden) position.value = '';
            syncVesselAssignments();
        };
        const filterDepartments = () => {
            const selected = department.value;
            departmentOptions.forEach(option => { option.hidden = Boolean(option.value && option.dataset.division && option.dataset.division !== division.value); });
            if (department.selectedOptions[0]?.hidden) department.value = '';
            if (selected && !department.value) department.dispatchEvent(new Event('change'));
            filterPositions();
            syncApprovalAuthority();
        };
        division.addEventListener('change', filterDepartments);
        department.addEventListener('change', filterPositions);
        position?.addEventListener('change', syncVesselAssignments);
        filterDepartments();
    }
    if (accessRole && accessHelp) {
        const syncAccessHelp = () => {
            accessHelp.textContent = accessRole.selectedOptions[0]?.dataset.description || 'Access controls are separate from the employee\'s job position.';
            syncApprovalAuthority();
        };
        accessRole.addEventListener('change', syncAccessHelp);
        syncAccessHelp();
    }
});
</script>
@endpush
