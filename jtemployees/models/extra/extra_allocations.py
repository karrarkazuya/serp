from odoo import models, fields, exceptions

class extra_allocations(models.Model):
    _name = 'jtemployees.extraallocations'
    _description = 'Extra allocations'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Char(string='Title', required=True, tracking=True)
    amount = fields.Float(string='Amount', tracking=True)
    employee_id = fields.Many2one('hr.employee', string='Employee', required=True, tracking=True)
    deleted = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
